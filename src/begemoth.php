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

require_once __DIR__ . '/handlers.php';
require_once __DIR__ . '/utilites.php';
require_once __DIR__ . '/plugins.php';
require_once __DIR__ . '/globals.php';
require_once JAXL_DIR . '/jaxl.php';

$config = array();
$dictionary = array();

$begemoth = null;
$conference = null;

$error_codes = array(
    E_WARNING           => 'E_WARNING',
    E_NOTICE            => 'E_NOTICE',
    E_USER_ERROR        => 'E_USER_ERROR',
    E_USER_WARNING      => 'E_USER_WARNING',
    E_USER_NOTICE       => 'E_USER_NOTICE',
    E_STRICT            => 'E_STRICT',
    E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
    E_DEPRECATED        => 'E_DEPRECATED',
    E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    E_ALL               => 'E_ALL'
);

function print_error($errno, $errstr, $errfile, $errline) {
    global $error_codes;

    $error_code = $error_codes[$errno];
    $error_source = basename($errfile) . ":" . $errline;
    _debug($error_code . " at " . $error_source . " - " . $errstr);
}

function print_help() {
    echo("Usage: " . basename(__FILE__) . " [options]\n"
       . "Available options:\n"
       . "  -c <config>  daemon configuration file\n"
       . "  -f           run begemoth in foreground\n"
       . "  -h           print this help and exit\n");
}

$options = getopt('c:fh');
if (isset($options['h'])) {
    print_help();
    exit(0);
}

if (!isset($options['c'])) {
    $options['c'] = CONFIG_DIR . '/config.json';
}

if (($config = load_json($options['c'])) == false) {
    _error('Failed to load "' . $options['c'] . '"');
    exit(1);
}

$daemon = !isset($options['f']);
if ($daemon) {
    daemonize();
}

// From this point imply config has all the options defined

$log_path = $daemon ? $config['private_dir'] . '/log/jaxl.log' : null;
$log_level = JAXL_INFO;

if ($config['debug']) {
    set_error_handler('print_error', E_ALL);
    $log_level = JAXL_DEBUG;
}

$strict_ssl = $config['strict_ssl'];
$stream_context = stream_context_create(array(
    'ssl' => array(
        'verify_peer'       => $strict_ssl,
        'verify_peer_name'  => $strict_ssl,
        'allow_self_signed' => !$strict_ssl
    )
));

$begemoth = new JAXL(array(
    'jid'            => $config['jid'],
    'pass'           => $config['password'],
    'host'           => $config['host'],
    'port'           => $config['port'],
    'force_tls'      => $config['tls'],
    'resource'       => $config['resource'],
    'priv_dir'       => $config['private_dir'],
    'log_path'       => $log_path,
    'log_level'      => $log_level,
    'stream_context' => $stream_context,
    'strict'         => false
));

$begemoth->require_xep(array(
    '0045',  // MUC
    '0203',  // Delayed Delivery
    '0199'   // XMPP Ping
));

$begemoth->add_cb('on_auth_success', 'on_auth_success');
$begemoth->add_cb('on_auth_failure', 'on_auth_failure');
$begemoth->add_cb('on_groupchat_message', 'on_groupchat_message');
$begemoth->add_cb('on_presence_stanza', 'on_presence_stanza');
$begemoth->add_cb('on_get_iq', 'on_get_iq');

$conference = new XMPPJid($config['conference'] . '/' . $config['nickname']);

_info('Loading dictionary from "' . $config['dictionary'] . '"');
if (($dictionary = load_json($config['dictionary'])) == false) {
    _warning('Failed to load dictionary');
    $dictionary = array();
}

_info('Loading plugins from "' . PLUGINS_DIR . '"');
if (!load_plugins(PLUGINS_DIR)) {
    _warning('Failed to load plugins');
}

$begemoth->start();

?>
