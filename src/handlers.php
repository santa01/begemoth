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

require_once __DIR__ . '/utilites.php';
require_once __DIR__ . '/plugins.php';
require_once __DIR__ . '/globals.php';
require_once JAXL_DIR . '/jaxl.php';

define('EV_SELF_ONLINE',  'self_online');
define('EV_SELF_OFFLINE', 'self_offline');
define('EV_USER_ONLINE',  'user_online');
define('EV_USER_OFFLINE', 'user_offline');

$roster_notified = array();
$roster_complete = false;

function response_lookup($command) {
    if (!isset($command['responses'])) {
        return null;
    }

    $available_variations = count($command['responses']);
    $variation_index = mt_rand(0, $available_variations - 1);

    _info('Found ' . $available_variations . ' responses, '
        . 'using #' . ($variation_index + 1));
    return $command['responses'][$variation_index];
}

function get_command_response($command) {
    global $dictionary;

    _info('Trying "plugins" section');
    if (isset($dictionary['plugins'][$command[0]])) {
        if (($output = dispatch_handler($command[0], @$command[1])) != null) {
            $response = response_lookup($dictionary['plugins'][$command[0]]);
            return strtr($response, array('{OUTPUT}' => $output));
        }
    }

    _info('Trying "extended_commands" section');
    if (isset($dictionary['extended_commands'][$command[0]])
        && isset($command[1])
    ) {
        return response_lookup($dictionary['extended_commands'][$command[0]]);
    }

    _info('Trying "commands" section');
    if (isset($dictionary['commands'][$command[0]])) {
        return response_lookup($dictionary['commands'][$command[0]]);
    }

    _info('Trying "unknown_commands" section');
    if (isset($dictionary['unknown_commands'])) {
        return response_lookup($dictionary['unknown_commands']);
    }

    return null;
}

function get_event_response($event) {
    global $dictionary;

    _info('Trying "events" section');
    if (isset($dictionary['events'][$event])) {
        return response_lookup($dictionary['events'][$event]);
    }

    return null;
}

// JAXL event handlers

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

    $stanza->body = htmlspecialchars_decode($stanza->body);
    $from = new XMPPJid($stanza->from);
    _info('Message (' . gmdate('Y-m-dTH:i:sZ') . ') '
        . $from->resource . ': ' . $stanza->body);

    preg_match('/^(\S+)\s*(.*)$/', trim($stanza->body), $command);
    array_shift($command);  // Remove full match from $command[0]
    if (isset($command[1]) && $command[1] == '') {
        unset($command[1]);  // Remove empty match of (.*) group
    }

    $command_parts = count($command);
    if ($command_parts > 0 && $command[0][0] == $config['command_prefix']) {
        $command[0] = substr($command[0], 1);  // Remove command prefix
        $lookup_start = microtime(true);

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

            $lookup_time = microtime(true) - $lookup_start;
            if ($lookup_time < $config['response_delay']) {
                sleepf($config['response_delay'] - $lookup_time);
            }

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
    $from_jid = $from->to_string();

    if ($stanza->exists('x', NS_MUC . '#user') !== false) {
        if (strtolower($from_jid) == strtolower($conference->to_string())) {
            if (isset($stanza->attrs['type'])
                && $stanza->attrs['type'] == 'unavailable'
            ) {
                $event = EV_SELF_OFFLINE;
            } else {
                $event = EV_SELF_ONLINE;
                $roster_complete = true;
            }
        } elseif (strtolower($from->bare) == strtolower($conference->bare)) {
            if ($roster_complete) {
                if (isset($stanza->attrs['type'])
                    && $stanza->attrs['type'] == 'unavailable'
                ) {
                    $event = EV_USER_OFFLINE;
                } else {
                    $event = EV_USER_ONLINE;
                }
            } else {
                $roster_notified[$from_jid] = true;
            }
        }
    }

    $valid_event = false;
    if (isset($event)) {
        if (isset($roster_notified[$from_jid])) {
            if ($event == EV_USER_OFFLINE || $event == EV_SELF_OFFLINE) {
                unset($roster_notified[$from_jid]);

                if ($event == EV_USER_OFFLINE) {
                    _info('User "' . $from->resource . '" went offline');
                    $valid_event = true;
                } else {
                    _info('Kicked out from conference');
                    shutdown();
                }
            }
        } elseif ($event == EV_USER_ONLINE || $event == EV_SELF_ONLINE) {
            $roster_notified[$from_jid] = true;
            $valid_event = true;

            if ($event == EV_USER_ONLINE) {
                _info('User "' . $from->resource . '" went online');
            } else {
                _info('Introducing myself');
            }
        }
    }

    if ($valid_event) {
        _info('Looking for event "' . $event . '"');
        if (($response = get_event_response($event)) != null) {
            $response = strtr($response,
                array('{USERNAME}' => $from->resource));
            sleepf($config['response_delay']);

            _info('Replying with: ' . $response);
            $begemoth->xeps['0045']->send_groupchat(
                $config['conference'], $response);
        } else {
            _info('I have nothing to reply');
        }
    }
}

function on_get_iq($stanza) {
    global $begemoth, $conference;

    if (!$stanza->from || !$stanza->id) {
        return;
    }

    $attrs = array(
        'id' => $stanza->id,
        'to' => $stanza->from,
        'from' => $stanza->to
    );

    if ($stanza->type == 'get') {
        if ($stanza->exists('query', 'jabber:iq:version')) {
            $attrs['type'] = 'result';

            $payload = new JAXLXml('query', 'jabber:iq:version');
            $payload->c('name', null, array(), NAME);

            $payload->up();
            $payload->c('version', null, array(),
                VERSION . ' (JAXL ' . JAXL::version . ')');

            $payload->up();
            $payload->c('os', null, array(),
                PHP_OS . ' (PHP ' . PHP_VERSION . ')');
        }
    }

    if (!isset($payload)) {
        $attrs['type'] = 'error';

        $payload = new JAXLXml('error',
            array('code' => 501, 'type' => 'cancel'));
        $payload->c('feature-not-implemented',
            'urn:ietf:params:xml:ns:xmpp-stanzas');
    }

    $response = $begemoth->get_iq_pkt($attrs, $payload);
    if ($stanza->exists('vCard', 'vcard-temp') !== false) {
        // As required by XEP-0054
        $response->top();
        $response->c('vCard', 'vcard-temp');
    }

    $begemoth->send($response);
}

?>
