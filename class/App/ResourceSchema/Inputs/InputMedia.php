<?php

namespace Wonder\App\ResourceSchema\Inputs;

use InvalidArgumentException;
use Wonder\App\Media\MediaFile;
use Wonder\App\Media\MediaTypes;
use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasMediaLibrary;
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
    use HasMediaLibrary;

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

    /** Sempre vero: il campo salva l'id di un media. */
    public function isMedia(): bool
    {
        return true;
    }

    /**
     * @return array{input: string, type: string, types: list<string>, multiple: bool, slug: string, alt: string, scope: string, scope_label: string}
     */
    public function mediaConfig(): array
    {
        $media = $this->mediaSchema();
        $type = (string) ($media['type'] ?? MediaTypes::IMAGE);

        return [
            'input' => 'picker',
            'type' => $type,
            'types' => [$type],
            'multiple' => false,
            'slug' => (string) ($media['slug'] ?? ''),
            'alt' => (string) ($media['alt'] ?? ''),
            'scope' => (string) ($media['scope'] ?? ''),
            'scope_label' => (string) ($media['scope_label'] ?? ''),
        ];
    }

    protected function element(): ElementField
    {
        $config = $this->mediaConfig();
        $type = $config['type'];
        $media = MediaFile::find($this->schema['value'] ?? null);

        return (new MediaPicker($this->name))
            ->mediaType($type)
            ->searchUrl(rtrim(defined('APP_URL') ? (string) APP_URL : '', '/').'/api/backend/media/search/')
            ->scope($config['scope'], $config['scope_label'])
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
