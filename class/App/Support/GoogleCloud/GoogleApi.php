<?php

namespace Wonder\App\Support\GoogleCloud;

/**
 * Un'API (o un artefatto) da abilitare nel progetto Google Cloud del sito.
 *
 *   GoogleApi::make('maps_javascript')
 *       ->name('Maps JavaScript API')
 *       ->service('maps-backend.googleapis.com')
 *       ->keyType(GoogleApi::KEY_BROWSER)
 *       ->fields(['gcp_client_api_key'])
 *       ->required()
 *       ->usedBy('ecommerce');
 *
 * `name` è il nome del prodotto come compare nella console Google, `service`
 * l'id del servizio (quello di `gcloud services enable`), `fields` le chiavi
 * di credenziale che devono essere compilate perché l'API funzioni.
 * La descrizione arriva dalla chiave lang `purpose()` (default
 * `google.apis.{key}.purpose`).
 */
final class GoogleApi
{
    public const KEY_BROWSER = 'browser';
    public const KEY_SERVER = 'server';
    public const KEY_OAUTH = 'oauth';

    private string $name = '';
    private ?string $service = null;
    private string $keyType = self::KEY_BROWSER;
    private string $purpose;
    /** @var list<string> */
    private array $fields = [];
    private bool $required = false;
    /** @var list<string> */
    private array $usedBy = [];

    private function __construct(private readonly string $key)
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_]{0,63}$/D', $key)) {
            throw new \InvalidArgumentException('Chiave API Google non valida: '.$key);
        }

        $this->purpose = 'google.apis.'.$key.'.purpose';
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function service(?string $service): self
    {
        if ($service !== null && !preg_match('/^[a-z0-9.-]+\.googleapis\.com$/D', $service)) {
            throw new \InvalidArgumentException('Servizio Google non valido: '.$service);
        }
        $this->service = $service;

        return $this;
    }

    public function keyType(string $keyType): self
    {
        if (!in_array($keyType, [self::KEY_BROWSER, self::KEY_SERVER, self::KEY_OAUTH], true)) {
            throw new \InvalidArgumentException('Tipo di chiave non valido: '.$keyType);
        }
        $this->keyType = $keyType;

        return $this;
    }

    /** Chiave lang della descrizione. */
    public function purpose(string $langKey): self
    {
        $this->purpose = $langKey;

        return $this;
    }

    /** @param list<string> $fields */
    public function fields(array $fields): self
    {
        $this->fields = array_values(array_unique(array_map('strval', $fields)));

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function usedBy(string ...$slugs): self
    {
        $this->usedBy = array_values(array_unique([...$this->usedBy, ...$slugs]));

        return $this;
    }

    public function key(): string { return $this->key; }
    public function getName(): string { return $this->name !== '' ? $this->name : $this->key; }
    public function getService(): ?string { return $this->service; }
    public function getKeyType(): string { return $this->keyType; }
    public function getPurpose(): string { return $this->purpose; }
    /** @return list<string> */
    public function getFields(): array { return $this->fields; }
    public function isRequired(): bool { return $this->required; }
    /** @return list<string> */
    public function getUsedBy(): array { return $this->usedBy; }

    /** Pagina della console dove si abilita l'API (o si crea il client OAuth). */
    public function consoleUrl(string $projectId = ''): string
    {
        $url = $this->service !== null
            ? 'https://console.cloud.google.com/apis/library/'.$this->service
            : 'https://console.cloud.google.com/auth/clients';

        return $projectId === '' ? $url : $url.'?project='.rawurlencode($projectId);
    }

    /**
     * Vero se tutti i `fields` sono compilati. Passa i valori salvati (riga
     * di security o .env), non `Credentials::api()`: lì `gcp_client_api_key`
     * vuota ricade su `gcp_api_key` e la chiave client sembrerebbe presente.
     *
     * @param array<string, mixed> $credentials
     */
    public function isConfigured(array $credentials): bool
    {
        foreach ($this->fields as $field) {
            if (trim((string) ($credentials[$field] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Unisce la stessa API dichiarata da un'altra sorgente: obbligatoria se
     * lo è per almeno una, campi e utilizzatori sommati. Nome, servizio,
     * tipo di chiave e descrizione restano quelli della prima dichiarazione.
     */
    public function merge(self $other): self
    {
        if ($other->key !== $this->key) {
            throw new \LogicException('API Google diverse: '.$this->key.' e '.$other->key);
        }

        $merged = clone $this;
        $merged->required = $this->required || $other->required;
        $merged->fields = array_values(array_unique([...$this->fields, ...$other->fields]));
        $merged->usedBy = array_values(array_unique([...$this->usedBy, ...$other->usedBy]));

        return $merged;
    }
}
