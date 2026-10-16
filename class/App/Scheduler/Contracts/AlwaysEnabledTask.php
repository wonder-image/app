<?php

namespace Wonder\App\Scheduler\Contracts;

/**
 * Attività che non si può sospendere: `Repository::sync()` riattiva la sua
 * pianificazione predefinita e il backend rifiuta di disattivarla. Separato
 * da `TaskInterface` per non rompere le attività già scritte.
 */
interface AlwaysEnabledTask
{
    public function alwaysEnabled(): bool;
}
