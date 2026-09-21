<?php

namespace Wonder\Backend\Support;

use Wonder\App\Path;
use Wonder\App\ResourceRegistry;

/**
 * Proxy backend della creazione rapida: autorizza, whitelista il sottoinsieme,
 * chiama lo store API della risorsa target come @system (token solo server-side)
 * e restituisce {id, label} letti dall'item creato dalla risposta dello store.
 * Vedi docs/app/concetti/form/quick-create.md.
 */
final class QuickCreateController
{
    /**
     * Tiene solo le chiavi del subset dichiarato. È una comodità UI, non un
     * confine di sicurezza: la creazione passa comunque dallo store API della
     * risorsa target, che accetta solo i propri campi `apiSchema('store')` ed è
     * gato dal permesso di creazione (verificato prima).
     *
     * @return array<string,mixed>
     */
    public static function whitelist(array $fields, array $post): array
    {
        return array_intersect_key($post, array_flip(array_values($fields)));
    }

    /**
     * @param array<string,mixed> $post          POST del modal (resource, quick_fields, quick_label, campi subset).
     * @param list<string>        $userAuthority authority dell'utente backend corrente.
     * @return array<string,mixed> {success:true,id,label} | {success:false,error,status}
     */
    public static function handle(array $post, array $userAuthority): array
    {
        $slug = trim((string) ($post['resource'] ?? ''));

        if ($slug === '' || !ResourceRegistry::has($slug)) {
            return ['success' => false, 'error' => 'Risorsa non trovata.', 'status' => 404];
        }

        $resourceClass = ResourceRegistry::resolve($slug);

        if (!QuickCreateAuthorizer::userCanCreate($resourceClass, $userAuthority)) {
            return ['success' => false, 'error' => 'Non sei autorizzato a creare questa risorsa.', 'status' => 403];
        }

        $fields = array_values(array_filter((array) ($post['quick_fields'] ?? []), 'is_string'));
        $labelField = trim((string) ($post['quick_label'] ?? ''));
        $values = self::whitelist($fields, $post);

        $token = self::systemToken();

        if ($token === '') {
            return ['success' => false, 'error' => 'Token API di sistema non disponibile.', 'status' => 500];
        }

        // Store API della risorsa target, chiamato lato server come @system.
        $url = rtrim((new Path())->api, '/').'/resource/'.$slug.'/';
        $response = curlJson($url, 'POST', $values, $token);

        if (!is_array($response) || empty($response['success'])) {
            return ['success' => false, 'error' => self::storeError($response), 'status' => 422];
        }

        $item = (array) ($response['response']['item'] ?? []);
        $id = (int) ($item['id'] ?? 0);

        if ($id <= 0) {
            return ['success' => false, 'error' => 'Creazione non riuscita.', 'status' => 422];
        }

        return ['success' => true, 'id' => $id, 'label' => self::deriveLabel($item, $labelField, $values, $id)];
    }

    private static function systemToken(): string
    {
        $user = function_exists('infoUser') ? infoUser('@system', 'username') : null;

        return trim((string) ($user->api_internal_user->token ?? ''));
    }

    /** Etichetta dell'opzione: dal campo label dell'item creato, con ripieghi. */
    private static function deriveLabel(array $item, string $labelField, array $values, int $id): string
    {
        if ($labelField !== '' && isset($item[$labelField]) && (string) $item[$labelField] !== '') {
            return (string) $item[$labelField];
        }

        if ($labelField !== '' && array_key_exists($labelField, $values)) {
            return (string) $values[$labelField];
        }

        return (string) ($item['name'] ?? $item['title'] ?? $id);
    }

    private static function storeError(mixed $response): string
    {
        if (!is_array($response)) {
            return 'Creazione non riuscita.';
        }

        $body = $response['response'] ?? '';

        if (is_string($body) && trim($body) !== '') {
            return $body;
        }

        if (is_array($body)) {
            $messages = [];
            array_walk_recursive($body, static function ($value) use (&$messages): void {
                if (is_string($value) && trim($value) !== '') {
                    $messages[] = $value;
                }
            });

            if ($messages !== []) {
                return implode(' ', array_slice($messages, 0, 3));
            }
        }

        return 'Creazione non riuscita.';
    }
}
