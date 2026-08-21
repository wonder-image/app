<?php
declare(strict_types=1);

/**
 * driver.php — agent harness for the wonder-image/app framework package.
 *
 * This package is a Composer LIBRARY (the Wonder framework core), not a site.
 * It has no `index.php`, no `.env`, no site `forge` binary — those live in the
 * sites that install it under vendor/. So `php forge start` cannot boot it as a
 * web server here. What CAN be driven standalone (and what every recent PR
 * actually touches) is the internal PHP surface: the classes under class/ and
 * the test suite under tests/. This driver reaches that surface three ways.
 *
 * Lives at <root>/.claude/skills/run-app/driver.php, so ROOT is 3 dirs up.
 *
 *   php .claude/skills/run-app/driver.php test [path]   run every test file under tests/ (or one file)
 *   php .claude/skills/run-app/driver.php forge [args]  boot the forge console (stand-in for the site `forge` bin)
 *   php .claude/skills/run-app/driver.php smoke         direct-invoke Wonder\Sql\Query hardening helpers
 */

$ROOT = dirname(__DIR__, 3);
$autoload = $ROOT . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php missing at {$ROOT} — run `composer install` first.\n");
    exit(2);
}

$cmd = $argv[1] ?? '';

switch ($cmd) {

    case 'test':
        // Each test file is a standalone script that defines its own top-level
        // constants (ROOT, APP_URL, …) and calls exit(). They CANNOT share one
        // process, so run each in its own `php` child and collect exit codes.
        $target = $argv[2] ?? null;
        $files = [];

        if ($target !== null && $target !== '') {
            $p = is_file($target) ? $target : $ROOT . '/' . ltrim($target, '/');
            if (!is_file($p)) {
                fwrite(STDERR, "No such test file: {$target}\n");
                exit(2);
            }
            $files[] = $p;
        } else {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($ROOT . '/tests', FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $fi) {
                if ($fi->isFile()
                    && substr($fi->getFilename(), -4) === '.php'
                    && $fi->getFilename() !== 'harness.php') {
                    $files[] = $fi->getPathname();
                }
            }
            sort($files);
        }

        $php = PHP_BINARY;
        $pass = 0;
        $fail = 0;
        $failed = [];

        foreach ($files as $f) {
            $out = [];
            $code = 0;
            exec(escapeshellarg($php) . ' ' . escapeshellarg($f) . ' 2>&1', $out, $code);
            $rel = ltrim(str_replace($ROOT, '', $f), '/');
            if ($code === 0) {
                $pass++;
                echo "  \u{2713} {$rel}\n";
            } else {
                $fail++;
                $failed[] = $rel;
                echo "  \u{2717} {$rel} (exit {$code})\n";
                foreach (array_slice($out, -4) as $l) {
                    echo "      {$l}\n";
                }
            }
        }

        echo "\n" . count($files) . " file, {$pass} ok, {$fail} falliti\n";
        exit($fail === 0 ? 0 : 1);

    case 'forge':
        // The framework ships Wonder\Console\Forge but no runnable `forge` file
        // (that is generated into each site). Boot it here so an agent can drive
        // every `php forge …` subcommand from inside this repo.
        require $autoload;
        $args = array_slice($argv, 2);
        array_unshift($args, 'forge');
        $forge = new \Wonder\Console\Forge();
        exit($forge->run(
            new \Symfony\Component\Console\Input\ArgvInput($args),
            new \Symfony\Component\Console\Output\ConsoleOutput()
        ));

    case 'smoke':
        // Direct invocation of the SQL-injection hardening helpers that the last
        // several commits added/patched (Wonder\Sql\Query). Pure static helpers,
        // no DB connection needed — proves the framework autoloads and the
        // security-critical surface behaves.
        require $autoload;
        $checks = [
            ['escapeIdentifier plain',       \Wonder\Sql\Query::escapeIdentifier('users'),                    '`users`'],
            ['escapeIdentifier breakout',    \Wonder\Sql\Query::escapeIdentifier('u`) ;DROP TABLE x-- '),     '`u``) ;DROP TABLE x-- `'],
            ['sanitizeLimit int',            \Wonder\Sql\Query::sanitizeLimit(5),                             '5'],
            ['sanitizeLimit offset,count',   \Wonder\Sql\Query::sanitizeLimit('0, 25'),                       '0, 25'],
            ['sanitizeLimit injection drop', \Wonder\Sql\Query::sanitizeLimit('1; DROP TABLE users-- '),      ''],
            ['sanitizeLimit union drop',     \Wonder\Sql\Query::sanitizeLimit('1 UNION SELECT pw FROM adm'),  ''],
        ];
        $fail = 0;
        foreach ($checks as [$label, $got, $exp]) {
            $ok = $got === $exp;
            if (!$ok) {
                $fail++;
            }
            printf("  %s %s\n", $ok ? "\u{2713}" : "\u{2717}", $label);
            if (!$ok) {
                printf("      expected=%s got=%s\n", var_export($exp, true), var_export($got, true));
            }
        }
        echo "\nsmoke: " . ($fail === 0 ? 'OK' : "{$fail} FAIL") . "\n";
        exit($fail === 0 ? 0 : 1);

    default:
        fwrite(STDERR,
            "Usage:\n" .
            "  php .claude/skills/run-app/driver.php test [path]    run every tests/**/*.php (or one file)\n" .
            "  php .claude/skills/run-app/driver.php forge [args]   boot the forge console\n" .
            "  php .claude/skills/run-app/driver.php smoke          direct-invoke Wonder\\Sql\\Query helpers\n"
        );
        exit(2);
}
