<?php // tests/harness.php
declare(strict_types=1);

// I test non spediscono email vere: `sendMail()` del core risponde sì e basta.
if (!defined('WONDER_NO_MAIL')) {
    define('WONDER_NO_MAIL', true);
}

// APP_ENV è obbligatorio (senza, `Environment::current()` lancia). Se il test non
// lo ha già, parte come `production`: è il valore che prima valeva per default,
// quindi le attese esistenti non cambiano. Impostato nell'ambiente vero, un
// `.env` caricato dopo (Dotenv immutabile) non lo sovrascrive.
if (!isset($_ENV['APP_ENV']) && !isset($_SERVER['APP_ENV']) && getenv('APP_ENV') === false) {
    $_ENV['APP_ENV'] = 'production';
    putenv('APP_ENV=production');
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
