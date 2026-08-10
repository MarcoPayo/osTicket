#!/usr/bin/env php
<?php
/*********************************************************************
    run.php

    Standalone test runner.

    Usage:
        php include/tests/run.php            # everything
        php include/tests/run.php visibility # just test.visibility.php

    Exits non-zero when anything fails, so it drops straight into CI or a
    pre-commit hook.

    These tests bootstrap nothing: no config, no database, no session. They
    exercise units of include/ in isolation. osTicket's own suite lives in
    setup/test/ and is not available in a deployment which has removed the
    setup directory.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

if (php_sapi_name() != 'cli')
    exit(1);

error_reporting(E_ALL);

require_once __DIR__.'/harness.php';

$selected = isset($argv[1]) ? $argv[1] : false;

$scripts = glob(__DIR__.'/test.*.php');
sort($scripts);

if ($selected) {
    $scripts = array_filter($scripts, function($s) use ($selected) {
        return strpos(basename($s), $selected) !== false;
    });
    if (!$scripts) {
        fwrite(STDERR, "no test matching '$selected'\n");
        exit(1);
    }
}

foreach ($scripts as $script)
    require_once $script;

exit(T::summary());
