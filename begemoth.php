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

require_once __DIR__ . '/include/JAXL/jaxl.php';
require_once __DIR__ . '/include/handlers.php';
require_once __DIR__ . '/include/utilites.php';

function print_help() {
    echo('Usage: ' . basename(__FILE__) . ' -c config [options]\n\n'
       . 'Available options:\n'
       . '\t-f: run begemoth in foreground\n'
       . '\t-h: print help\n\n');
}

$options = getopt('c:fh');
if (array_key_exists('h', $options)) {
    print_help();
    exit(0);
}

if (!array_key_exists('c', $options)) {
    print_help();
    exit(1);
}

_info('Loading configuration from "' . $options['c'] . '"');
if (($config = load_json($options['c'])) == false) {
    _error('Failed to load "' . $options['c'] . '"');
    exit(1);
}

_info('Loading dictionary from "' . $config['dictionary'] . '"');
if (($dictionary = load_json($config['dictionary'])) == false) {
    _error('Failed to load "' . $config['dictionary'] . '"');
    exit(1);
}

if (!array_key_exists('f', $options)) {
    _info('Redirecting logs to "' . $log_path . '"');
    $log_path = $config['private_dir'] . '/log/' . 'jaxl.log';

    _info('Forking to background');
    daemonize();
}

$begemoth = new JAXL(array(
    'jid'       => $config['jid'],
    'pass'      => $config['password'],
    'host'      => $config['host'],
    'port'      => $config['port'],
    'force_tls' => $config['tls'],
    'resource'  => $config['resource'],
    'log_level' => $config['verbose'] ? JAXL_DEBUG : JAXL_WARNING,
    'log_path'  => isset($log_path) ? $log_path : null,
    'priv_dir'  => $config['private_dir'],
    'strict'    => false
));

$begemoth->require_xep(array(
    '0045',  // MUC
    '0203',  // Delayed Delivery
    '0199'   // XMPP Ping
));

$begemoth->add_cb('on_auth_success', on_auth_success);
$begemoth->add_cb('on_auth_failure', on_auth_failure);
$begemoth->add_cb('on_groupchat_message', on_groupchat_message);
$begemoth->add_cb('on_presence_stanza', on_presence_stanza);
$begemoth->add_cb('on_get_iq', on_get_iq);

$conference = new XMPPJid($config['conference'] . '/' . $config['nickname']);

_info('Starting main loop');
$begemoth->start();

?>
