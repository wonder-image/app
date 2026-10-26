<?php

namespace Wonder\Themes\Concerns;

/**
 * Lettura comune dello schema di Alert per i renderer dei temi:
 * livello → tipo, titolo di ripiego, id, attributi e messaggio già escapati.
 * Il markup e le icone restano del singolo tema.
 */
trait ReadsAlertSchema
{
    /**
     * @return array{message:string, title:string, type:string, dismissible:bool, id:string, attributes:string}
     */
    protected function readAlertSchema(object $class): array
    {
        $schema = $class->getSchema();
        $title = trim((string) ($schema['title'] ?? ''));
        $type = $this->alertType(strtolower((string) ($schema['level'] ?? 'info')));

        return [
            'message' => $this->escapeWithLineBreaks((string) ($schema['message'] ?? '')),
            'title' => $this->escape($title !== '' ? $title : ucfirst($type)),
            'type' => $type,
            'dismissible' => (bool) ($schema['dismissible'] ?? true),
            'id' => $this->escape($this->resolveId($schema['id'] ?? null)),
            'attributes' => $this->renderAttributes($schema['attributes'] ?? null),
        ];
    }

    protected function alertType(string $level): string
    {
        return match ($level) {
            'error' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            default => 'info',
        };
    }
}
