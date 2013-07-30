<?php

function var_string($variable)
{
    ob_start();
    var_dump($variable);
    return ob_get_clean();
}

function eval_string($code) {
    ob_start();
    eval($code);
    return ob_get_clean();
}

function daemonize() {
    if (pcntl_fork()) {
        exit(0);
    }

    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);

    posix_setsid();
}

function load_json($json_file) {
    if (($json = file_get_contents($json_file)) === false) {
        return false;
    }

    return json_decode($json, true);
}

function sleepf($timeout) {
    usleep(intval($timeout * 1000000));
}

?>
