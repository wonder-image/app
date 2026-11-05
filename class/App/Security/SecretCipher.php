<?php

namespace Wonder\App\Security;

/**
 * Cifratura autenticata dei segreti salvati nel database (XChaCha20-Poly1305
 * di libsodium, nonce casuale di 24 byte). Il formato è
 * `enc:v1:<kid>:<payload>`: `kid` sono i primi 8 caratteri esadecimali dello
 * SHA-256 della chiave, `payload` è nonce + testo cifrato in base64url senza
 * padding. Il contesto (per esempio `security:mail_password`) è il dato
 * associato: un valore copiato in un'altra colonna non si decifra.
 *
 * La chiave corrente cifra e decifra, la precedente solo decifra (rotazione).
 * Una chiave non valida (non 64 caratteri esadecimali) vale come assente e si
 * legge con `invalidKeys()`. Senza sodium o senza chiave corrente il cifrario
 * non è disponibile: chi lo usa decide se salvare in chiaro. Nessun metodo
 * mette valori o testo cifrato nei messaggi delle eccezioni.
 */
final class SecretCipher
{
    public const PREFIX = 'enc:v1:';

    public const ENV_KEY = 'APP_ENCRYPTION_KEY';

    public const ENV_PREVIOUS_KEY = 'APP_ENCRYPTION_KEY_PREVIOUS';

    private const NONCE_BYTES = 24;

    private const TAG_BYTES = 16;

    private ?string $currentKey = null;

    private ?string $currentKid = null;

    /** @var array<string, list<string>> */
    private array $keys = [];

    /** @var list<string> */
    private array $invalid = [];

    private bool $sodium;

    public function __construct(?string $current, ?string $previous = null, ?bool $sodium = null)
    {
        $this->sodium = $sodium ?? self::sodiumAvailable();

        $currentKey = $this->parse($current, self::ENV_KEY);

        if ($currentKey !== null) {
            $this->currentKey = $currentKey;
            $this->currentKid = self::kidFor($currentKey);
            $this->keys[$this->currentKid][] = $currentKey;
        }

        $previousKey = $this->parse($previous, self::ENV_PREVIOUS_KEY);

        if ($previousKey !== null && $previousKey !== $currentKey) {
            $this->keys[self::kidFor($previousKey)][] = $previousKey;
        }
    }

    /** Le chiavi da `$_ENV` (`APP_ENCRYPTION_KEY`, `APP_ENCRYPTION_KEY_PREVIOUS`). */
    public static function fromEnv(): self
    {
        return new self(
            (string) ($_ENV[self::ENV_KEY] ?? ''),
            (string) ($_ENV[self::ENV_PREVIOUS_KEY] ?? '')
        );
    }

    /** Una chiave nuova nel formato atteso: 64 caratteri esadecimali. */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function sodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt');
    }

    /** Si può cifrare: sodium presente e chiave corrente valida. */
    public function available(): bool
    {
        return $this->sodium && $this->currentKey !== null;
    }

    public function sodium(): bool
    {
        return $this->sodium;
    }

    public function currentKid(): ?string
    {
        return $this->currentKid;
    }

    /**
     * Le variabili impostate ma con un valore non valido.
     *
     * @return list<string>
     */
    public function invalidKeys(): array
    {
        return $this->invalid;
    }

    public static function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /** Il `kid` di un valore cifrato, `null` se il formato non torna. */
    public static function kidOf(string $value): ?string
    {
        if (!self::isEncrypted($value)) {
            return null;
        }

        $kid = explode(':', substr($value, strlen(self::PREFIX)), 2)[0];

        return preg_match('/^[0-9a-f]{8}$/', $kid) === 1 ? $kid : null;
    }

    /** Il valore è cifrato con una chiave che questo cifrario conosce. */
    public function knows(string $value): bool
    {
        $kid = self::kidOf($value);

        return $kid !== null && isset($this->keys[$kid]);
    }

    /** Cifrato con una chiave conosciuta che non è la corrente: va ricifrato. */
    public function needsRotation(string $value): bool
    {
        return $this->knows($value) && self::kidOf($value) !== $this->currentKid;
    }

    public function encrypt(string $plaintext, string $context): string
    {
        if (!$this->available()) {
            throw new SecretCipherException('Cifratura non disponibile: chiave o estensione sodium mancante.');
        }

        if (self::isEncrypted($plaintext)) {
            throw new SecretCipherException('Il valore è già cifrato.');
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $context, $nonce, (string) $this->currentKey);

        return self::PREFIX.$this->currentKid.':'.self::base64UrlEncode($nonce.$cipher);
    }

    /**
     * Il testo in chiaro. Un valore senza prefisso torna com'è (dato non
     * ancora migrato); uno cifrato che non si apre solleva l'eccezione.
     */
    public function decrypt(string $value, string $context): string
    {
        if (!self::isEncrypted($value)) {
            return $value;
        }

        if (!$this->sodium) {
            throw new SecretCipherException('Decifratura non disponibile: estensione sodium mancante.');
        }

        $kid = self::kidOf($value);

        if ($kid === null) {
            throw new SecretCipherException('Valore cifrato in un formato non valido.');
        }

        if (!isset($this->keys[$kid])) {
            throw new SecretCipherException('Valore cifrato con una chiave sconosciuta ('.$kid.').');
        }

        $raw = self::base64UrlDecode(substr($value, strlen(self::PREFIX) + 9));

        if ($raw === null || strlen($raw) < self::NONCE_BYTES + self::TAG_BYTES) {
            throw new SecretCipherException('Valore cifrato in un formato non valido.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $cipher = substr($raw, self::NONCE_BYTES);

        foreach ($this->keys[$kid] as $key) {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $context, $nonce, $key);

            if (is_string($plaintext)) {
                return $plaintext;
            }
        }

        throw new SecretCipherException('Valore cifrato non autentico o chiave errata ('.$kid.').');
    }

    private function parse(?string $value, string $name): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[0-9a-fA-F]{64}$/', $value) !== 1) {
            $this->invalid[] = $name;

            return null;
        }

        return (string) hex2bin($value);
    }

    private static function kidFor(string $key): string
    {
        return substr(hash('sha256', $key), 0, 8);
    }

    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
