<?php

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
    $log_path = $config['private_dir'] . '/log/' . 'jaxl.log';
    _info('Logs are redirected to "' . $log_path . '"');

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

$begemoth->add_cb('on_auth_success',      function() { on_auth_success(); });
$begemoth->add_cb('on_auth_failure',      function($reason) { on_auth_failure($reason); });
$begemoth->add_cb('on_groupchat_message', function($stanza) { on_groupchat_message($stanza); });
$begemoth->add_cb('on_presence_stanza',   function($stanza) { on_presence_stanza($stanza); });

$conference = new XMPPJid($config['conference'] . '/' . $config['nickname']);

_info('Starting main loop');
$begemoth->start();

?>
