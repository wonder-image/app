<?php

namespace Wonder\App\ResourceSchema\Inputs;

use InvalidArgumentException;
use Wonder\App\Media\MediaFile;
use Wonder\App\Media\MediaTypes;
use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\MediaPicker;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Un media della libreria: la colonna del record ne tiene l'id.
 *
 * Dal form si sceglie un media già in libreria o si carica un file nuovo,
 * che diventa un media del tipo del campo. Il salvataggio lo risolve
 * `Wonder\App\Media\MediaInputs`, lato server, quando si salva il record.
 *
 * ```php
 * FormField::key('image_id')->mediaImage()
 *     ->mediaSlug('{slug}')   // lo slug (e il nome del file) del media creato dal campo
 *     ->mediaAlt('{name}');   // il suo testo alternativo
 * ```
 *
 * I modelli si riempiono con i valori del record. Lo slug vale solo alla
 * creazione del media; il testo alternativo segue il record finché nessuno
 * lo scrive a mano in libreria.
 */
class InputMedia extends Input
{
    protected string $helper = 'media';

    /** Uno dei tipi di `MediaTypes`. */
    public function mediaType(string $type): static
    {
        if (!MediaTypes::has($type)) {
            throw new InvalidArgumentException("Tipo di media sconosciuto: {$type}");
        }

        $this->schema['media']['type'] = $type;

        return $this;
    }

    /**
     * Modello dello slug del media creato dal campo, es. `{slug}` o
     * `{slug}-retro`. Se lo slug è già di un altro media prende `-2`,
     * `-3`, ... Senza modello lo slug viene dal nome del file caricato.
     */
    public function mediaSlug(string $template): static
    {
        $this->schema['media']['slug'] = trim($template);

        return $this;
    }

    /** Modello del testo alternativo del media creato dal campo, es. `{name}`. */
    public function mediaAlt(string $template): static
    {
        $this->schema['media']['alt'] = trim($template);

        return $this;
    }

    /** @return array{type: string, slug: string, alt: string} */
    public function mediaConfig(): array
    {
        $media = (array) ($this->schema['media'] ?? []);

        return [
            'type' => (string) ($media['type'] ?? MediaTypes::IMAGE),
            'slug' => (string) ($media['slug'] ?? ''),
            'alt' => (string) ($media['alt'] ?? ''),
        ];
    }

    protected function element(): ElementField
    {
        $type = $this->mediaConfig()['type'];
        $media = MediaFile::find($this->schema['value'] ?? null);

        return (new MediaPicker($this->name))
            ->mediaType($type)
            ->searchUrl(rtrim(defined('APP_URL') ? (string) APP_URL : '', '/').'/api/backend/media/search/')
            ->media($media !== null && $media->type === $type ? $media->summary() : null);
    }

    /** L'id come testo; un valore che non è un id non arriva al campo. */
    protected function elementValue(): mixed
    {
        $value = $this->schema['value'] ?? null;

        return is_scalar($value) && preg_match('/^\d+$/D', trim((string) $value)) === 1 && (int) $value > 0
            ? (string) (int) $value
            : '';
    }
}
