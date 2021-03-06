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

require_once __DIR__ . '/../plugins.php';
require_once __DIR__ . '/../globals.php';
require_once JAXL_DIR . '/jaxl.php';

function bash_handler($argument) {
    global $config;

    if (isset($argument)) {
        return null;
    }

    $timeout = $config['bash']['timeout'];
    $context = stream_context_create(
        array('http' => array('timeout' => $timeout)));

    $bash_uri = 'http://bash.im/forweb/?u';
    $quote = @file_get_contents($bash_uri, false, $context);
    if ($quote === false) {
         _warning('HTTP request failed:'
            . ' uri = "' . $bash_uri
            . '", timeout = ' . $timeout);
        return null;
    }

    $quote = strtr($quote, array("\n" => ''));
    $quote = preg_replace("/^.*;\">(.*)<' \+ '\/div>.*$/U", '$1', $quote);
    $quote = preg_replace("/<' \+ 'br( )?(\/)?>/", "\n", $quote);
    return htmlspecialchars_decode($quote);
}

_info('Registering "bash" plugin');
if (!register_handler('bash', 'bash_handler')) {
    _warning('Registration failed');
}

?>
