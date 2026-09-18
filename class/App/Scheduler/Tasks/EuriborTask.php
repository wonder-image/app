<?php

namespace Wonder\App\Scheduler\Tasks;

use Wonder\App\Scheduler\{AbstractTask, Context};

/** Adapter for sites already providing the reusable Euribor::sync() service. */
class EuriborTask extends AbstractTask
{
    protected string $modelClass = 'App\\Models\\Site\\Euribor';
    public function key(): string { return 'wonder.euribor'; }
    public function label(): string { return 'Aggiornamento Euribor'; }
    public function expression(): string { return '0 12 * * 1-5'; }
    public function run(Context $context): array
    {
        if (!is_callable([$this->modelClass, 'sync'])) { throw new \RuntimeException('Servizio Euribor::sync() non disponibile nel sito.'); }
        $context->checkDeadline();
        $result = $this->modelClass::sync();
        if (!is_array($result) || ($result['success'] ?? true) === false || (int) ($result['status'] ?? 200) >= 400) {
            throw new \RuntimeException('Sincronizzazione Euribor fallita.');
        }
        return $result;
    }
}
