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

# Refer to https://developer.musixmatch.com for details.
define('API_HOST', 'api.musixmatch.com/ws/1.1');
define('GOOGLE_HOST', 'www.google.com');

$cache_artists = array();
$cache_albums = array();
$cache_tracks = array();
$cache_lyrics = array();

function call_api($method, $params, $timeout) {
    $context = stream_context_create(
        array('http' => array('timeout' => $timeout)));

    $uri_params = array();
    foreach ($params as $name => $value) {
        array_push($uri_params, $name . '=' . $value);
    }

    $uri_params = implode('&', $uri_params);
    $uri = 'http://' . API_HOST . '/' . $method . '?' . $uri_params;

    $ts = microtime(true);
    if (($result = @file_get_contents($uri, false, $context)) === false) {
        _warning('Request timed out: ' . $uri);
        return null;
    }

    $request_time = round(microtime(true) - $ts, 3);
    _info('Request finished in ' . $request_time . ' seconds: ' . $uri);

    $response = json_decode($result);
    return $response->{'message'}->{'body'};
}

function trim_text($text, $max_paragraphs) {
    $trimmed_text = '';
    $paragraphs = 0;

    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        $trimmed_text .= $line . "\n";

        if ($line == '') {
            $paragraphs++;
        }
        if ($paragraphs == $max_paragraphs) {
            break;
        }
    }

    return $trimmed_text;
}

function search_link($artist, $track) {
    $search_artist = '';
    foreach (explode(' ', $artist) as $token) {
        $search_artist .= $token . '+';
    }

    $search_track = '';
    foreach (explode(' ', $track) as $token) {
        $search_track .= '+' . $token;
    }

    return 'http://' . GOOGLE_HOST . '/search?q='
        . htmlspecialchars($search_artist) . '-'
        . htmlspecialchars($search_track);
}

function jukebox_handler($argument) {
    global $config;
    global $cache_artists, $cache_albums, $cache_tracks, $cache_lyrics;

    if (isset($argument)) {
        return null;
    }

    $artists = $config['jukebox']['artists'];
    $timeout = $config['jukebox']['timeout'];
    $apikey = $config['jukebox']['apikey'];

    $artist_name = $artists[mt_rand(0, count($artists) - 1)];

    # Request an artist id.

    if (!array_key_exists($artist_name, $cache_artists)) {
        $response = call_api('artist.search', array(
            'apikey'    => $apikey,
            'q_artist'  => urlencode($artist_name),
            'page_size' => 1
        ), $timeout);

        $artist = $response->{'artist_list'}[0]->{'artist'};
        $cache_artists[$artist_name] = array(
            $artist->{'artist_id'},
            $artist->{'artist_name'}
        );
    }

    $artist = $cache_artists[$artist_name];
    $artist_id = $artist[0];
    $artist_name = $artist[1];

    # Fetch a list of albums.

    if (!array_key_exists($artist_id, $cache_albums)) {
        $response = call_api('artist.albums.get', array(
            'apikey'    => $apikey,
            'artist_id' => $artist_id,
            'page_size' => 100
        ), $timeout);

        $albums = array();
        foreach ($response->{'album_list'} as $album) {
            array_push($albums, array(
                $album->{'album'}->{'album_id'},
                $album->{'album'}->{'album_name'}
            ));
        }

        $cache_albums[$artist_id] = $albums;
    }

    $album = $cache_albums[$artist_id];
    _info('Found ' . count($albums) . ' tracks for artist ' . $artist_name);

    $album = $albums[mt_rand(0, count($albums) - 1)];
    $album_id = $album[0];
    $album_name = $album[1];

    # Fetch a list of tracks.

    if (!array_key_exists($album_id, $cache_tracks)) {
        $response = call_api('album.tracks.get', array(
            'apikey'       => $apikey,
            'album_id'     => $album_id,
            'f_has_lyrics' => true,
            'page_size'    => 50
        ), $timeout);

        $tracks = array();
        foreach ($response->{'track_list'} as $track) {
            array_push($tracks, array(
                $track->{'track'}->{'track_id'},
                $track->{'track'}->{'track_name'}
            ));
        }

        $cache_tracks[$album_id] = $tracks;
    }

    $tracks = $cache_tracks[$album_id];
    _info('Found ' . count($tracks) . ' tracks in album ' . $album_name);

    $track = $tracks[mt_rand(0, count($tracks) - 1)];
    $track_id = $track[0];
    $track_name = $track[1];

    # Fetch lyrics.

    if (!array_key_exists($track_id, $cache_lyrics)) {
        $response = call_api('track.lyrics.get', array(
            'apikey'   => $apikey,
            'track_id' => $track_id
        ), $timeout);

        $lyrics = $response->{'lyrics'}->{'lyrics_body'};
        $lyrics = substr($lyrics, 0, strpos($lyrics, '...'));

        $cache_lyrics[$track_id] = $lyrics;
    }

    # Compose output.

    _info('Baking lyrics for ' . $track_name);
    $lyrics = trim_text(
        $cache_lyrics[$track_id],
        $config['jukebox']['max_paragraphs']);

    $output = htmlspecialchars_decode(
        $artist_name . ' - ' . $track_name . "\n\n" . $lyrics);

    if ($config['jukebox']['google_link']) {
        $output .= search_link($artist_name, $track_name);
    }

    return $output;
}

_info('Registering "jukebox" plugin');
if (!register_handler('song', 'jukebox_handler')) {
    _warning('Registration failed');
}

?>
