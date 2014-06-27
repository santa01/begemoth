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

define('URI_REGEX',   '/<h3 class=\"r\"><a href=\"\/url\?q=(.+)&amp;/U');
define('SONGS_REGEX', '/<li><a href=\"(.+)\" title=\"(.+) Lyrics\">/U');
define('SONG_REGEX',  '/<script src=\"\/\/srv\.tonefuse.+\"><\/script><br>(.+)<script>/Us');

define('LYRICS_HOST', 'www.sing365.com');
define('GOOGLE_HOST', 'www.google.com');

function match_content($uri, $regex, $timeout = 3, $all = false) {
    $context = stream_context_create(
        array('http' => array('timeout' => $timeout)));
    if (($result = @file_get_contents($uri, false, $context)) === false) {
        _warning('HTTP request failed:'
            . ' uri = "' . $uri
            . '", timeout = ' . $timeout);
        return null;
    }

    if ($all) {
        $match = preg_match_all($regex, $result, $match_result);
    } else {
        $match = preg_match($regex, $result, $match_result);
    }

    if ($match === false) {
        _warning('Regex match failed:'
            . ' uri = "' . $uri
            . '", regex = "' . $regex
            . '", all = ' . $all);
        return null;
    } else {
        return $match_result;
    }
}

function jukebox_handler($argument) {
    global $config;

    if (isset($argument)) {
        return null;
    }

    $artists = $config['jukebox']['artists'];
    $timeout = $config['jukebox']['timeout'];

    $artist = $artists[mt_rand(0, count($artists) - 1)];
    $search_artist = '';
    foreach (explode(' ', $artist) as $token) {
        $search_artist .= $token . '+';
    }

    _info('Looking for artist ' . $artist);
    $search_uri = 'http://' . GOOGLE_HOST . '/search?q='
        . htmlspecialchars('site:' . LYRICS_HOST . '+'
        . $search_artist . 'lyrics&num=1');
    $artist_match = match_content($search_uri, URI_REGEX, $timeout);
    if ($artist_match == null) {
        return null;
    }

    $songs_match = match_content(
        $artist_match[1], SONGS_REGEX, $timeout, true);
    if ($songs_match == null) {
        return null;
    }

    _info('Found ' . count($songs_match[1]) . ' songs');
    $song_index = mt_rand(0, count($songs_match[1]) - 1);
    $song_name = $songs_match[2][$song_index];

    _info('Looking for ' . $song_name . ' lyrics');
    $song_uri = 'http://' . LYRICS_HOST . $songs_match[1][$song_index];
    $song_match = match_content($song_uri, SONG_REGEX, $timeout);
    if ($song_match == null) {
        return null;
    }

    $max_lines = $config['jukebox']['max_lines'];
    $max_paragraphs = $config['jukebox']['max_paragraphs'];
    $lines = 0;
    $paragraphs = 0;

    $song = strtr($song_match[1], array('<br>' => '' , '<BR>' => ''));
    $song = preg_replace('/<!-.*->/Us', '', $song);
    $song_cut = '';

    foreach (explode("\n", $song) as $line) {
        $line = trim($line);
        if ($line == '' && $lines == 0) {
            continue;  // Skip empty lines at the beginning
        }

        if ($line == '') {
            $paragraphs++;
        } else {
            $song_cut .= $line;
            $lines++;
        }

        if (($paragraphs == 0 && $lines == $max_lines)
            || $paragraphs == $max_paragraphs
        ) {
            break;
        }

        $song_cut .= "\n";
    }

    $output = htmlspecialchars_decode(
        $artist . ' - ' . $song_name . "\n\n" . $song_cut);

    if ($config['jukebox']['google_link']) {
        $search_song = '';
        foreach (explode(' ', $song_name) as $token) {
            $search_song .= '+' . $token;
        }

        $output .= "\n" . 'http://' . GOOGLE_HOST . '/search?q='
            . htmlspecialchars($search_artist) . '-'
            . htmlspecialchars($search_song);
    }

    return $output;
}

_info('Registering "jukebox" plugin');
if (!register_handler('song', 'jukebox_handler')) {
    _warning('Registration failed');
}

?>
