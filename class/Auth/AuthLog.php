<?php

    namespace Wonder\Auth;

    /**
     * Log eventi di autenticazione.
     */
    class AuthLog
    {
        // Scrive un evento nel log
        public static function write(string $event, ?int $userId = null, ?string $area = null, ?bool $success = null, array $meta = []): void
        {
            // JSON valido anche quando meta è vuoto
            $metaJson = 'null';
            if (!empty($meta)) {
                $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $metaJson = $encoded !== false ? $encoded : 'null';
            }

            $values = [
                'user_id' => $userId,
                'event' => $event,
                'area' => $area ?? '',
                'success' => is_null($success) ? null : ($success ? 1 : 0),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'meta' => $metaJson
            ];

            sqlInsert('auth_log', $values);
        }
    }
