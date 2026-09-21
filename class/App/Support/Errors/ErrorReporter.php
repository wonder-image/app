<?php

namespace Wonder\App\Support\Errors;

use Throwable;
use Wonder\App\Models\System\ErrorReport;

/**
 * Raccoglie gli errori ripetuti che non hanno un documento su cui restare.
 *
 * Ogni errore ha un'impronta fatta di servizio, azione e punto del codice: la
 * stessa impronta è sempre la stessa riga, con un contatore. L'email parte una
 * volta sola, alla prima occorrenza; le altre alzano solo il contatore. Quando
 * qualcuno segna l'errore risolto la riga si chiude, e se il problema torna
 * l'email riparte: è il modo per accorgersi che la correzione non ha tenuto.
 *
 * Qui stanno i guasti tecnici, quelli che deve vedere chi sviluppa: un
 * provider che non risponde, una chiamata che va in timeout. Quello che
 * riguarda chi usa il sito — un ordine da controllare, una spedizione ferma —
 * non è un errore ma una notifica, e non passa di qui.
 *
 * Gli indirizzi a cui scrivere li conosce il sito, non il core: si passano con
 * `recipientsUsing()`. Senza risolutore resta solo la riga.
 */
final class ErrorReporter
{
    /** @var callable(): array<int, string>|null */
    private static $recipients = null;

    /** Impronta dell'errore: stesso problema, stessa riga. */
    public static function fingerprint(string $service, string $action, Throwable|string $error): string
    {
        $parts = [trim($service), trim($action)];

        if ($error instanceof Throwable) {
            $parts[] = get_class($error);
            $parts[] = basename($error->getFile());
            $parts[] = (string) $error->getLine();
        } else {
            $parts[] = trim($error);
        }

        return sha1(implode('|', $parts));
    }

    /**
     * Registra l'errore e avvisa quando serve.
     *
     * @return bool vero se è partita un'email (prima occorrenza o riapertura)
     */
    public static function report(
        string $service,
        string $action,
        Throwable|string $error,
        array $context = []
    ): bool {
        $fingerprint = self::fingerprint($service, $action, $error);
        $message = $error instanceof Throwable ? $error->getMessage() : trim($error);
        $now = date('Y-m-d H:i:s');
        $existing = self::find($fingerprint);

        if ($existing === null) {
            sqlInsert(ErrorReport::$table, [
                'fingerprint' => $fingerprint,
                'service' => trim($service),
                'action' => trim($action),
                'message' => $message,
                'context' => (string) json_encode($context, JSON_UNESCAPED_UNICODE),
                'occurrences' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            return self::notify($service, $action, $message, $context, 1, $fingerprint);
        }

        $wasResolved = trim((string) ($existing['resolved_at'] ?? '')) !== '';
        $occurrences = $wasResolved ? 1 : (int) ($existing['occurrences'] ?? 0) + 1;

        sqlModify(ErrorReport::$table, [
            'message' => $message,
            'context' => (string) json_encode($context, JSON_UNESCAPED_UNICODE),
            'occurrences' => $occurrences,
            'last_seen_at' => $now,
            // Riaperto: torna a essere un problema di oggi.
            'resolved_at' => $wasResolved ? '' : (string) ($existing['resolved_at'] ?? ''),
            'resolved_by' => $wasResolved ? 0 : (int) ($existing['resolved_by'] ?? 0),
            'first_seen_at' => $wasResolved ? $now : (string) ($existing['first_seen_at'] ?? $now),
        ], 'id', (int) $existing['id']);

        if (!$wasResolved) {
            return false;
        }

        return self::notify($service, $action, $message, $context, $occurrences, $fingerprint);
    }

    /** Segna risolto: se il problema torna, l'avviso riparte. */
    public static function resolve(int $id, int $userId): bool
    {
        $result = sqlModify(ErrorReport::$table, [
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $userId,
        ], 'id', $id);

        return !empty($result->success);
    }

    /** Errori ancora aperti, dal più recente. @return list<array<string, mixed>> */
    public static function open(): array
    {
        $rows = ErrorReport::find(['deleted' => 'false'], null, 'last_seen_at', 'DESC');

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $rows = isset($rows['id']) ? [$rows] : array_values(array_filter($rows, 'is_array'));

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => trim((string) ($row['resolved_at'] ?? '')) === ''
        ));
    }

    /** Chi riceve gli avvisi. @return list<string> */
    public static function recipients(): array
    {
        if (self::$recipients === null) {
            return [];
        }

        $addresses = (self::$recipients)();

        if (!is_array($addresses)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($address): string => trim((string) $address), $addresses),
            static fn (string $address): bool => $address !== ''
                && filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    /** Chi segnala decide i destinatari; `null` li toglie. */
    public static function recipientsUsing(?callable $resolver): void
    {
        self::$recipients = $resolver;
    }

    private static function find(string $fingerprint): ?array
    {
        $row = ErrorReport::find(['fingerprint' => $fingerprint, 'deleted' => 'false'], 1);

        return is_array($row) && $row !== [] ? $row : null;
    }

    /** Una mail per gruppo di destinatari, con quello che serve per capire. */
    private static function notify(
        string $service,
        string $action,
        string $message,
        array $context,
        int $occurrences,
        string $fingerprint
    ): bool {
        $recipients = self::recipients();

        if ($recipients === [] || !function_exists('sendMail')) {
            return false;
        }

        $subject = '['.$service.'] '.$action;
        $body = '<p>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p>';

        if ($context !== []) {
            $body .= '<pre>'.htmlspecialchars(
                (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ENT_QUOTES,
                'UTF-8'
            ).'</pre>';
        }

        $body .= '<p>Occorrenze: '.$occurrences.'</p>';
        $sent = false;

        foreach ($recipients as $recipient) {
            if (sendMail(null, $recipient, $subject, $body)) {
                $sent = true;
            }
        }

        if ($sent) {
            sqlModify(ErrorReport::$table, ['notified_at' => date('Y-m-d H:i:s')], 'fingerprint', $fingerprint);
        }

        return $sent;
    }
}
