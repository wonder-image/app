<?php

namespace Wonder\App\Scheduler\Tasks;

use Wonder\App\Credentials;
use Wonder\App\Scheduler\{AbstractTask, Context};
use Wonder\App\Support\GooglePlaces\{DatabaseGooglePlacesStore, GooglePlacesStore, GooglePlacesSync, HttpPlaceDetailsClient, PlaceDetailsClient};

/**
 * Allinea le sedi con un `place_id` a Google Places (orari, stato,
 * valutazione e recensioni). È sempre attiva: non si può sospendere dal
 * backend, e senza la chiave `GCP_API_KEY` ogni esecuzione si limita a
 * registrare che è stata saltata.
 *
 * Parametri: `apply_hours` (bool) sostituisce gli orari della sede con
 * quelli di Google invece di segnalare solo le differenze; `language`
 * (codice lingua, vuoto = lingua predefinita del sito) per le recensioni.
 */
class GooglePlacesTask extends AbstractTask
{
    public function __construct(
        private readonly ?PlaceDetailsClient $client = null,
        private readonly ?GooglePlacesStore $store = null
    ) {
    }

    public function key(): string { return 'wonder.google-places'; }
    public function label(): string { return 'Allineamento sedi con Google'; }
    public function expression(): string { return '30 3 * * *'; }
    public function enabled(): bool { return true; }
    public function alwaysEnabled(): bool { return true; }
    public function defaultParameters(): array { return ['apply_hours' => false, 'language' => '']; }

    public function validate(array $parameters): array
    {
        $unknown = array_diff(array_keys($parameters), ['apply_hours', 'language']);

        if ($unknown !== []) {
            throw new \InvalidArgumentException('Parametri non ammessi: '.implode(', ', $unknown).'.');
        }

        $apply = $parameters['apply_hours'] ?? false;
        $language = $parameters['language'] ?? '';

        if (!is_bool($apply)) {
            throw new \InvalidArgumentException('apply_hours deve essere true o false.');
        }

        if (!is_string($language) || ($language !== '' && preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $language) !== 1)) {
            throw new \InvalidArgumentException('language deve essere un codice lingua (es. it, en-GB) o vuoto.');
        }

        return ['apply_hours' => $apply, 'language' => $language];
    }

    public function run(Context $context): array
    {
        $parameters = $this->validate($context->parameters + $this->defaultParameters());

        if ($this->client === null && trim((string) (Credentials::api()->gcp_api_key ?? '')) === '') {
            $context->log('Chiave GCP_API_KEY assente: allineamento saltato.');

            return ['locations' => 0, 'synced' => 0, 'failed' => 0, 'applied' => 0, 'differences' => 0, 'cleared' => 0, 'skipped' => 'missing_api_key'];
        }

        $language = $parameters['language'] !== '' ? $parameters['language'] : (function_exists('__dl') ? (string) __dl() : 'it');
        $sync = new GooglePlacesSync(
            $this->client ?? static fn () => new HttpPlaceDetailsClient(),
            $this->store ?? new DatabaseGooglePlacesStore()
        );

        return $sync->run($parameters['apply_hours'], $language, $context->log(...), $context->checkDeadline(...));
    }
}
