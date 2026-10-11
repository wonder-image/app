<?php

namespace Wonder\App\Scheduler\Tasks;

use Wonder\App\Scheduler\{AbstractTask, Context};
use Wonder\App\Support\GooglePlaces\{DatabaseGooglePlacesStore, GooglePlacesStore, GooglePlacesSync, HttpPlaceDetailsClient, PlaceDetailsClient};

/**
 * Allinea le sedi con un `place_id` a Google Places (orari, stato,
 * valutazione e recensioni). Spento finché non lo si attiva dal backend.
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
        $language = $parameters['language'] !== '' ? $parameters['language'] : (function_exists('__dl') ? (string) __dl() : 'it');
        $sync = new GooglePlacesSync(
            $this->client ?? static fn () => new HttpPlaceDetailsClient(),
            $this->store ?? new DatabaseGooglePlacesStore()
        );

        return $sync->run($parameters['apply_hours'], $language, $context->log(...), $context->checkDeadline(...));
    }
}
