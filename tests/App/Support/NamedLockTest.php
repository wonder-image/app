<?php
/** php tests/App/Support/NamedLockTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\NamedLock;
use Wonder\App\Support\NamedLockConnection;

final class FakeNamedLockConnection implements NamedLockConnection
{
    /** @var array<string, bool> */
    public array $held = [];
    /** @var string[] */
    public array $log = [];

    public function acquire(string $name, int $timeout): bool
    {
        $this->log[] = 'acquire '.$name.' '.$timeout;

        if (isset($this->held[$name])) {
            return false;
        }

        $this->held[$name] = true;
        return true;
    }

    public function release(string $name): void
    {
        $this->log[] = 'release '.$name;
        unset($this->held[$name]);
    }
}

check('esegue il callback e rilascia', function () {
    $connection = new FakeNamedLockConnection();
    $value = NamedLock::runOn($connection, 'gestionale:invoices', fn () => 'fatto', 5);
    return $value === 'fatto'
        && $connection->log === ['acquire gestionale:invoices 5', 'release gestionale:invoices']
        && $connection->held === [];
});

check('lock già preso: NOT_ACQUIRED senza eseguire', function () {
    $connection = new FakeNamedLockConnection();
    $connection->held['gestionale:invoices'] = true;
    $executed = false;
    $value = NamedLock::runOn($connection, 'gestionale:invoices', function () use (&$executed) { $executed = true; });
    return $value === NamedLock::NOT_ACQUIRED && $executed === false;
});

check('eccezione: rilascio e rilancio', function () {
    $connection = new FakeNamedLockConnection();
    try {
        NamedLock::runOn($connection, 'cron', function () { throw new RuntimeException('boom'); });
        return false;
    } catch (RuntimeException) {
        return $connection->held === [] && end($connection->log) === 'release cron';
    }
});

check('nomi lunghi ridotti a 64 caratteri al massimo e stabili', function () {
    $long = str_repeat('gestionale:', 10);
    $short = NamedLock::lockName($long);
    return strlen($short) <= 64 && $short === NamedLock::lockName($long) && NamedLock::lockName('breve') === 'breve';
});

check('nome vuoto rifiutato', function () {
    try {
        NamedLock::runOn(new FakeNamedLockConnection(), '  ', fn () => null);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
});

summary();
