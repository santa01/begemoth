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

function dice_handler($argument) {
    if (!isset($argument)) {
        return null;
    }

    if (!preg_match('/^(\d*)?d(\d+)(\+(\d*))?$/', $argument, $parameters)){
        return null;
    }

    $throws = ($parameters[1] != '') ? intval($parameters[1]) : 1;
    $faces = intval($parameters[2]);
    $modifier = isset($parameters[4]) ? intval($parameters[4]) : 0;

    if ($throws < 1 || $faces < 1 || $modifier < 0) {
        return null;
    }

    $total = $modifier;
    for ($i = 0; $i < $throws; $i++) {
        $total += mt_rand(1, $faces);
    }

    return "\n\n" . $total;
}

_info('Registering "dice" plugin');
if (!register_handler('dice', 'dice_handler')) {
    _warning('Failed to register "dice" plugin');
}

?>
