<?php
/*********************************************************************
    harness.php

    Minimal assertion + class-extraction helpers for the standalone test
    suite in this directory.

    osTicket's own suite lives in setup/test/, which is removed from this
    deployment after install (scp/admin.inc.php nags while it exists), and
    its runner bootstraps the database, i18n and table definitions before a
    single assertion runs. These tests deliberately depend on none of that:
    no bootstrap, no database, no config.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class T {

    static $pass = 0;
    static $fail = 0;
    static $failures = array();
    static $suite = '';

    static function suite($name) {
        self::$suite = $name;
        printf("\n%s\n", $name);
    }

    /**
     * Strict comparison. $want defaults to true so boolean predicates read
     * as `T::check('thing happens', $predicate)`.
     */
    static function check($label, $got, $want=true) {
        if ($got === $want) {
            self::$pass++;
            printf("  ok    %s\n", $label);
            return true;
        }
        self::$fail++;
        self::$failures[] = array(self::$suite, $label,
            var_export($got, true), var_export($want, true));
        printf("  FAIL  %s\n", $label);
        return false;
    }

    /**
     * Asserts $fn completes without throwing. Errors are reported with the
     * exception class and message so a regression names itself.
     */
    static function nothrow($label, callable $fn) {
        try {
            $fn();
        }
        catch (Throwable $e) {
            self::$fail++;
            self::$failures[] = array(self::$suite, $label,
                get_class($e).': '.$e->getMessage(), 'no exception');
            printf("  FAIL  %s\n", $label);
            return false;
        }
        self::$pass++;
        printf("  ok    %s\n", $label);
        return true;
    }

    static function summary() {
        if (self::$failures) {
            printf("\n%d FAILURE(S)\n", count(self::$failures));
            print("-------------------------------------------------------\n");
            foreach (self::$failures as $f) {
                list($suite, $label, $got, $want) = $f;
                printf("%s: %s\n     got:  %s\n     want: %s\n",
                    $suite, $label, $got, $want);
            }
        }
        printf("\n%d passed, %d failed\n", self::$pass, self::$fail);
        return self::$fail ? 1 : 0;
    }
}

/**
 * Pull a single top-level class out of a PHP source file and evaluate it.
 *
 * include/class.forms.php cannot simply be included: it drags in most of the
 * application and expects a live config and database. Extracting only the
 * class under test keeps these tests fast and dependency free, at the cost
 * of relying on the file's layout.
 *
 * Class boundaries are located by `class`/`abstract class` declarations at
 * column 0, which is how class.forms.php is written throughout. A class
 * indented inside a conditional would not be found -- there are none.
 *
 * The extracted source is eval'd rather than written to a temp file so the
 * inline `?> ... <?php` blocks inside methods keep working; eval() begins in
 * PHP mode, so the source must NOT be prefixed with an opening tag.
 */
function extract_class($path, $name) {
    if (!is_readable($path))
        throw new RuntimeException("$path: not readable");

    $lines = file($path);
    $start = null;
    foreach ($lines as $i => $line) {
        if (preg_match('/^(abstract\s+)?class\s+'.preg_quote($name, '/').'\b/', $line)) {
            $start = $i;
            break;
        }
    }
    if ($start === null)
        throw new RuntimeException("$name: not found in $path");

    $end = count($lines);
    for ($i = $start + 1; $i < count($lines); $i++) {
        if (preg_match('/^(abstract\s+)?(class|interface|trait)\s/', $lines[$i])) {
            $end = $i;
            break;
        }
    }

    return implode('', array_slice($lines, $start, $end - $start));
}

/**
 * Convenience wrapper: extract and define the class in one step.
 */
function load_class($path, $name) {
    if (class_exists($name, false))
        return;
    eval(extract_class($path, $name));
}
