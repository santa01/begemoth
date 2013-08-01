<?php

/**
 * Copyright (c) 2013 Pavlo Lavrenenko
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

require_once __DIR__ . '/JAXL/jaxl.php';
require_once __DIR__ . '/utilites.php';

$roster_notified = array();
$roster_complete = false;

function response_lookup($command) {
    $result = array();

    foreach ($command as $section => $variations) {
        switch ($section) {
            case 'responses':
            case 'actions':
                $available_variations = count($command[$section]);
                $variation_index = mt_rand(0, $available_variations - 1);
                _info('Found ' . $available_variations . ' ' . $section . ', '
                    . 'using #' . ($variation_index + 1));
                $result[$section] = $command[$section][$variation_index];
                break;
        }
    }

    if (count($result) != 0) {
        return @$result['responses'] . eval_string(@$result['actions']);
    }

    return null;
}

function get_command_response($command) {
    global $dictionary;

    if (count($command) > 1
        && array_key_exists('extended_commands', $dictionary)
        && array_key_exists($command[0], $dictionary['extended_commands'])
    ) {
        _info('Trying "extended_commands" section');
        return response_lookup($dictionary['extended_commands'][$command[0]]);
    }

    // Fallback to simple command even if argument supplied
    if (array_key_exists('commands', $dictionary)
        && array_key_exists($command[0], $dictionary['commands'])
    ) {
        _info('Trying "commands" section');
        return response_lookup($dictionary['commands'][$command[0]]);
    }

    if (array_key_exists('unknown_commands', $dictionary)) {
        _info('Trying "unknown_commands" section');
        return response_lookup($dictionary['unknown_commands']);
    }

    return null;
}

function get_event_response($event) {
    global $dictionary;

    if (array_key_exists('events', $dictionary)
        && array_key_exists($event, $dictionary['events'])
    ) {
        _info('Trying "events" section');
        return response_lookup($dictionary['events'][$event]);
    }

    return null;
}

function on_auth_success() {
    global $begemoth, $conference;

    _info('Authentification successful for "'
        . $begemoth->full_jid->to_string() . '"');
    _info('Joining conference "' . $conference->to_string() . '"');
    $begemoth->xeps['0045']->join_room($conference);
}

function on_auth_failure($reason) {
    global $begemoth;

    _info('Authentification failed: ' . $reason);
    $begemoth->send_end_stream();
}

function on_groupchat_message($stanza) {
    global $begemoth, $config;
    $delay = $stanza->exists('delay', NS_DELAYED_DELIVERY);

    if (!$stanza->body || !$stanza->from || $delay) {
        return;
    }

    $from = new XMPPJid($stanza->from);
    _info('Message (' . gmdate('Y-m-dTH:i:sZ') . ') '
         . $from->resource . ': ' . $stanza->body);

    preg_match('/^(\S+)\s*(.*)$/', trim($stanza->body), $command);
    array_shift($command);  // Remove full match from $command[0]
    $command_parts = count($command);

    if ($command_parts > 0 && $command[0][0] == '!') {
        _info('Looking for command "' . $command[0] . '"');

        if (($response = get_command_response($command)) != null) {
            switch ($command_parts) {
                case 2:
                    $response = strtr($response,
                        array('{ARGUMENT}' => $command[1]));
                default:
                    $response = strtr($response,
                        array('{COMMAND}' => $stanza->body));
                    $response = strtr($response,
                        array('{USERNAME}' => $from->resource));
                    break;
            }

            sleepf($config['response_delay']);

            _info('Replying with: ' . $response);
            $begemoth->xeps['0045']->send_groupchat($config['conference'],
                $response);
        } else {
            _info('I have nothing to reply');
        }
    }
}

function on_presence_stanza($stanza) {
    global $begemoth, $config, $conference;
    global $roster_notified, $roster_complete;
    $from = new XMPPJid($stanza->from);

    if (($x = $stanza->exists('x', NS_MUC . '#user')) !== false) {
        if (strtolower($from->to_string())
            == strtolower($conference->to_string())
        ) {
            _info('Got "available" presence notification from myself');
            $roster_complete = true;
            $event = 'on_self_online';
        } elseif (strtolower($from->bare) == strtolower($conference->bare)) {
            if ($roster_complete) {
                if (isset($stanza->attrs['type'])) {
                    if ($stanza->attrs['type'] == 'unavailable') {
                       _info('Got "unavailable" presence notification from "'
                           . $from->resource . '"');
                       $event = 'on_user_offline';
                    }
                } else {
                    _info('Got "available" presence notification from "'
                        . $from->resource . '"');
                    $event = 'on_user_online';
                }
            } else {
                $roster_notified[$from->to_string()] = true;
            }
        }
    }

    if ($roster_complete && isset($event)) {
        if (array_key_exists($from->to_string(), $roster_notified)) {
            switch ($event) {
                case 'on_user_online':
                    _info('User "' . $from->resource . '" updated the status');
                    return;  // Status change, not really went online

                case 'on_user_offline':
                    _info('User "' . $from->resource . '" went offline');
                    unset($roster_notified[$from->to_string()]);
                    break;
            }
        } else {
            // Somebody really went online
            _info('User "' . $from->resource . '" went online');
            $roster_notified[$from->to_string()] = true;
        }

        _info('Looking for event "' . $event . '"');

        if (($response = get_event_response($event)) != null) {
            $response = strtr($response,
                array('{USERNAME}' => $from->resource));

            sleepf($config['response_delay']);

            _info('Replying with: ' . $response);
            $begemoth->xeps['0045']->send_groupchat($config['conference'],
                $response);
        } else {
            _info('I have nothing to reply');
        }
    }
}

?>
