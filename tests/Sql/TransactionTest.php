<?php
/** php tests/Sql/TransactionTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Sql\Transaction;
use Wonder\Sql\TransactionConnection;

final class FakeTransactionConnection implements TransactionConnection
{
    /** @var string[] */
    public array $log = [];

    public function __construct(private string $key = 'fake:1') {}

    public function id(): string { return $this->key; }
    public function begin(): void { $this->log[] = 'begin'; }
    public function commit(): void { $this->log[] = 'commit'; }
    public function rollback(): void { $this->log[] = 'rollback'; }
    public function savepoint(string $name): void { $this->log[] = 'savepoint '.$name; }
    public function rollbackToSavepoint(string $name): void { $this->log[] = 'rollback to '.$name; }
    public function releaseSavepoint(string $name): void { $this->log[] = 'release '.$name; }
}

check('commit e valore restituito', function () {
    $connection = new FakeTransactionConnection();
    $value = Transaction::runOn($connection, fn () => 42);
    return $value === 42 && $connection->log === ['begin', 'commit'] && !Transaction::activeOn($connection);
});

check('eccezione: rollback e rilancio', function () {
    $connection = new FakeTransactionConnection();
    try {
        Transaction::runOn($connection, function () { throw new RuntimeException('boom'); });
        return false;
    } catch (RuntimeException $exception) {
        return $exception->getMessage() === 'boom'
            && $connection->log === ['begin', 'rollback']
            && !Transaction::activeOn($connection);
    }
});

check('attiva durante il callback', function () {
    $connection = new FakeTransactionConnection();
    $inside = null;
    Transaction::runOn($connection, function () use ($connection, &$inside) { $inside = Transaction::activeOn($connection); });
    return $inside === true;
});

check('annidamento con savepoint e rilascio', function () {
    $connection = new FakeTransactionConnection();
    Transaction::runOn($connection, function () use ($connection) {
        Transaction::runOn($connection, fn () => null);
    });
    return $connection->log === ['begin', 'savepoint wi_sp_1', 'release wi_sp_1', 'commit'];
});

check('errore interno gestito: torna al savepoint, esterno conferma', function () {
    $connection = new FakeTransactionConnection();
    Transaction::runOn($connection, function () use ($connection) {
        try {
            Transaction::runOn($connection, function () { throw new LogicException('interno'); });
        } catch (LogicException) {
        }
    });
    return $connection->log === ['begin', 'savepoint wi_sp_1', 'rollback to wi_sp_1', 'commit'];
});

check('errore interno non gestito: annulla tutto', function () {
    $connection = new FakeTransactionConnection();
    try {
        Transaction::runOn($connection, function () use ($connection) {
            Transaction::runOn($connection, function () { throw new LogicException('interno'); });
        });
    } catch (LogicException) {
    }
    return $connection->log === ['begin', 'savepoint wi_sp_1', 'rollback to wi_sp_1', 'rollback'];
});

check('connessioni diverse indipendenti', function () {
    $a = new FakeTransactionConnection('fake:a');
    $b = new FakeTransactionConnection('fake:b');
    Transaction::runOn($a, function () use ($b) {
        Transaction::runOn($b, fn () => null);
    });
    return $a->log === ['begin', 'commit'] && $b->log === ['begin', 'commit'];
});

summary();
