<?php // tests/harness.php
declare(strict_types=1);

// I test non spediscono email vere: `sendMail()` del core risponde sì e basta.
if (!defined('WONDER_NO_MAIL')) {
    define('WONDER_NO_MAIL', true);
}

$GLOBALS['__tests'] = 0;
$GLOBALS['__failures'] = 0;

function check(string $name, callable $fn): void
{
    $GLOBALS['__tests']++;
    try {
        $ok = $fn();
        if ($ok === false) {
            $GLOBALS['__failures']++;
            echo "  ✗ {$name}\n";
        } else {
            echo "  ✓ {$name}\n";
        }
    } catch (\Throwable $e) {
        $GLOBALS['__failures']++;
        echo "  ✗ {$name} — {$e->getMessage()}\n";
    }
}

function summary(): void
{
    $t = $GLOBALS['__tests'];
    $f = $GLOBALS['__failures'];
    echo "\n{$t} test, {$f} falliti\n";
    exit($f === 0 ? 0 : 1);
}
