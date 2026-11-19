<?php

namespace Wonder\App\Migrations;

/** Esito di `Migrator::runPending()`. `status`: done | busy | failed. */
class MigrationRunResult
{
    public bool $success = false;
    public string $status = 'done';
    public ?string $error = null;
    public int $batch = 0;

    /** @var array<int, array<string, mixed>> migrazioni applicate (o, con `pretend`, che si applicherebbero) */
    public array $applied = [];

    /** @var array<int, array<string, mixed>> ancora in attesa dopo l'esecuzione */
    public array $pending = [];

    /** @var array<int, array<string, mixed>> non eseguite: distruttive senza `force` fuori da local */
    public array $skipped = [];

    /** @var array<string, mixed>|null esito di `runTables()` con `with_schema` */
    public ?array $schema = null;

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'error' => $this->error,
            'batch' => $this->batch,
            'applied' => $this->applied,
            'pending' => $this->pending,
            'skipped' => $this->skipped,
            'schema' => $this->schema,
        ];
    }
}
