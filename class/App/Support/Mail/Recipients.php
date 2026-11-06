<?php

namespace Wonder\App\Support\Mail;

use InvalidArgumentException;

/**
 * Destinatari in copia di `sendMail()`, `queueMail()` e `mailDeliver()`.
 * Accetta una stringa (anche più indirizzi separati da `,` o `;`) o una
 * lista; tiene solo gli indirizzi validi, senza duplicati (maiuscole
 * comprese) e senza chi è già destinatario: `to` esce dai CC, `to` e CC
 * escono dai BCC. Funzioni pure: niente rete né database.
 */
final class Recipients
{
    /** Chiavi accettate in `$options` di `sendMail()`: una nuova opzione si aggiunge qui. */
    public const OPTIONS = ['cc', 'bcc'];

    /**
     * @param string|iterable<mixed>|null $addresses
     * @param list<string> $exclude indirizzi già usati altrove
     * @return list<string>
     */
    public static function normalize($addresses, array $exclude = []): array
    {
        $seen = [];
        foreach ($exclude as $address) {
            foreach (self::split($address) as $part) {
                $seen[strtolower($part)] = true;
            }
        }

        $list = [];
        foreach (self::split($addresses) as $address) {
            $key = strtolower($address);
            if (isset($seen[$key]) || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $seen[$key] = true;
            $list[] = $address;
        }

        return $list;
    }

    /**
     * Opzioni di `sendMail()` normalizzate: sempre tutte le chiavi di
     * `OPTIONS`. Una chiave sconosciuta è un errore di chi chiama.
     *
     * @return array{cc: list<string>, bcc: list<string>}
     */
    public static function options(array $options, $to): array
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Opzioni email sconosciute: '.implode(', ', $unknown).'.');
        }

        $to = self::split($to);
        $cc = self::normalize($options['cc'] ?? null, $to);
        $bcc = self::normalize($options['bcc'] ?? null, array_merge($to, $cc));

        return ['cc' => $cc, 'bcc' => $bcc];
    }

    /** Testo per `mail_log` (`a@x.it, b@x.it`), null se la lista è vuota. */
    public static function format(array $addresses): ?string
    {
        return $addresses === [] ? null : implode(', ', $addresses);
    }

    /** @return list<string> */
    private static function split($addresses): array
    {
        if ($addresses === null || $addresses === '') {
            return [];
        }

        $values = is_iterable($addresses) ? $addresses : [$addresses];
        $parts = [];

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            foreach (preg_split('/[,;]/', (string) $value) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }
        }

        return $parts;
    }
}
