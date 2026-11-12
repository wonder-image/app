<?php

namespace Wonder\Elements\Form\Components;

use InvalidArgumentException;
use Wonder\App\Media\MediaTypes;
use Wonder\Elements\Form\Field;

/**
 * Il campo che tiene l'id di un media della libreria: anteprima del media
 * scelto, scelta dalla libreria, caricamento di un file nuovo, rimozione.
 *
 * Solo configurazione: il valore è l'id, `media()` porta i dati
 * dell'anteprima (di solito `MediaFile::summary()`), `searchUrl()`
 * l'indirizzo della ricerca del selettore. Esiste solo nel tema Bootstrap,
 * come il Repeater: il frontend non sceglie media.
 */
class MediaPicker extends Field
{
    public string $type = 'media';

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->mediaType(MediaTypes::IMAGE);
    }

    /** Uno dei tipi di `MediaTypes`: decide estensioni, peso e ricerca. */
    public function mediaType(string $type): self
    {
        if (!MediaTypes::has($type)) {
            throw new InvalidArgumentException("Tipo di media sconosciuto: {$type}");
        }

        return $this->schema('media_type', $type);
    }

    /**
     * Il media già scelto, per l'anteprima: `id`, `name` e, se c'è,
     * `thumbnail`. `null` quando il campo è vuoto o il media non c'è più.
     *
     * @param array{id: int|string, name: string, thumbnail?: string}|null $media
     */
    public function media(?array $media): self
    {
        return $this->schema('media', $media);
    }

    /** Indirizzo della ricerca nella libreria (`api/backend/media/search`). */
    public function searchUrl(string $url): self
    {
        return $this->schema('search_url', trim($url));
    }
}
