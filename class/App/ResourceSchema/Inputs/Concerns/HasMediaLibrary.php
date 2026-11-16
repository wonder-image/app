<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Le impostazioni comuni ai campi che salvano media della libreria:
 * slug e testo alternativo dei media creati, sezione del selettore.
 * Tutto vive in `schema['media']`.
 */
trait HasMediaLibrary
{
    /**
     * Modello dello slug del media creato dal campo, es. `{slug}` o
     * `{slug}-retro`. Se lo slug è già di un altro media prende `-2`,
     * `-3`, ... Senza modello lo slug viene dal nome del file caricato.
     */
    public function mediaSlug(string $template): static
    {
        $this->schema['media']['slug'] = trim($template);
        $this->schema['media']['enabled'] = true;

        return $this;
    }

    /** Modello del testo alternativo del media creato dal campo, es. `{name}`. */
    public function mediaAlt(string $template): static
    {
        $this->schema['media']['alt'] = trim($template);
        $this->schema['media']['enabled'] = true;

        return $this;
    }

    /**
     * La sezione del campo: il selettore della libreria parte dai media
     * caricati da `$table` o usati lì, e `$label` dà il nome all'opzione.
     * La Resource la imposta da sola con la sua tabella; `''` mostra
     * subito tutta la libreria.
     */
    public function mediaScope(string $table, string $label = ''): static
    {
        $this->schema['media']['scope'] = trim($table);
        $this->schema['media']['scope_label'] = trim($label);

        return $this;
    }

    /** Vero se la sezione è stata scelta, anche vuota. */
    public function hasMediaScope(): bool
    {
        return array_key_exists('scope', (array) ($this->schema['media'] ?? []));
    }

    /** Vero se il campo salva media della libreria. */
    public function isMedia(): bool
    {
        return (bool) ($this->schema['media']['enabled'] ?? false);
    }

    /** @return array<string, mixed> */
    protected function mediaSchema(): array
    {
        return (array) ($this->schema['media'] ?? []);
    }
}
