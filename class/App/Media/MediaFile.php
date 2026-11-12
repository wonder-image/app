<?php

namespace Wonder\App\Media;

use Throwable;
use Wonder\App\Support\Asset;
use Wonder\Elements\Media\Image;
use Wonder\Support\Html\Entity;

/**
 * Un media della libreria letto dal suo id: quello che serve a una pagina
 * per stamparlo (indirizzo, testo alternativo, tipo, nome del file).
 *
 * ```php
 * $cover = MediaFile::find($product['image_id']);          // null se non c'è
 * $covers = MediaFile::many(array_column($products, 'image_id')); // una lettura sola
 *
 * echo $cover?->image()?->displaySizes('(min-width: 768px) 50vw, 100vw');
 * echo $manual?->url();
 * ```
 *
 * Un id vuoto, sconosciuto o di un media eliminato dà `null`, mai
 * un'eccezione: una pagina pubblica non si rompe per un media tolto.
 */
final class MediaFile
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $alt,
        public readonly string $file,
    ) {
    }

    public static function find(mixed $id): ?self
    {
        $id = self::id($id);

        return $id === null ? null : (self::many([$id])[$id] ?? null);
    }

    /**
     * I media di più id con una lettura sola, per id. Gli id non validi e
     * quelli che non ci sono più mancano dal risultato.
     *
     * @param array<int|string, mixed> $ids
     * @return array<int, self>
     */
    public static function many(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(self::id(...), $ids))));

        if ($ids === []) {
            return [];
        }

        try {
            $rows = MediaLibrary::store()->rows($ids);
        } catch (Throwable) {
            return [];
        }

        $media = [];

        foreach ($ids as $id) {
            $file = isset($rows[$id]) ? self::fromRow($rows[$id]) : null;

            if ($file !== null) {
                $media[$id] = $file;
            }
        }

        return $media;
    }

    /** @param array<string, mixed> $row una riga di `MediaStore` */
    public static function fromRow(array $row): ?self
    {
        $id = self::id($row['id'] ?? null);
        $type = (string) ($row['type'] ?? '');

        if ($id === null || !MediaTypes::has($type)) {
            return null;
        }

        return new self(
            $id,
            $type,
            (string) ($row['slug'] ?? ''),
            self::plain((string) ($row['name'] ?? '')),
            self::plain((string) ($row['alt'] ?? '')),
            basename((string) ($row['file'] ?? ''))
        );
    }

    /** Testo salvato (con le entità del database) come testo semplice. */
    public static function plain(string $text): string
    {
        return trim(Entity::decode($text));
    }

    public function isImage(): bool
    {
        return $this->type === MediaTypes::IMAGE;
    }

    /** Il nome del file salvato, senza cartella. */
    public function fileName(): string
    {
        return $this->file;
    }

    public function extension(): string
    {
        return MediaTypes::extension($this->file);
    }

    /** L'indirizzo del file senza versione, o stringa vuota se il media non ha file. */
    public function path(): string
    {
        return $this->file === '' ? '' : self::directoryUrl($this->type).$this->file;
    }

    /** L'indirizzo del file, con la versione di `Asset::version()`. */
    public function url(): string
    {
        $path = $this->path();

        return $path === '' ? '' : Asset::version($path);
    }

    /**
     * L'indirizzo di un'anteprima per il backend: la misura più piccola di
     * un'immagine (l'originale finché è in coda), il file di un'icona,
     * niente per documenti e video.
     */
    public function thumbnailUrl(): string
    {
        $path = $this->path();

        if ($path === '' || !in_array($this->type, [MediaTypes::IMAGE, MediaTypes::ICON], true)) {
            return '';
        }

        $widths = self::widths();

        if (!$this->isImage() || $widths === []) {
            return Asset::version($path);
        }

        $variant = self::directoryUrl($this->type).pathinfo($this->file, PATHINFO_FILENAME).'-'.min($widths).'.'.$this->extension();

        return Asset::version(Variants::url($variant, $path));
    }

    /**
     * L'Element `Image` del media, con il testo alternativo e le misure del
     * sito; `null` se il media non è un'immagine o un'icona, o non ha file.
     */
    public function image(): ?Image
    {
        if ($this->path() === '' || !in_array($this->type, [MediaTypes::IMAGE, MediaTypes::ICON], true)) {
            return null;
        }

        // Testo semplice con `attr()`: `Image::alt()` lo escaperebbe una
        // seconda volta, prima del renderer.
        $image = Image::src($this->path())->attr('alt', $this->alt);

        if ($this->isImage() && self::widths() !== []) {
            $image->sizes(self::widths())->hasWebP(defined('RESPONSIVE_IMAGE_WEBP') && RESPONSIVE_IMAGE_WEBP === true);
        }

        return $image;
    }

    /**
     * I dati che il selettore del backend mostra di un media.
     *
     * @return array{id: int, name: string, slug: string, alt: string, type: string, thumbnail: string}
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name !== '' ? $this->name : $this->file,
            'slug' => $this->slug,
            'alt' => $this->alt,
            'type' => $this->type,
            'thumbnail' => $this->thumbnailUrl(),
        ];
    }

    /** Indirizzo della cartella del tipo sotto `assets/upload`, con la barra finale. */
    public static function directoryUrl(string $type): string
    {
        return rtrim(defined('APP_URL') ? (string) APP_URL : '', '/').'/assets/upload/'.MediaTypes::folder($type).'/';
    }

    /** @return list<int> le larghezze responsive del sito */
    private static function widths(): array
    {
        $sizes = defined('RESPONSIVE_IMAGE_SIZES') ? RESPONSIVE_IMAGE_SIZES : [];

        return is_array($sizes)
            ? array_values(array_filter(array_map('intval', $sizes), static fn (int $size): bool => $size > 0))
            : [];
    }

    private static function id(mixed $id): ?int
    {
        if (is_int($id)) {
            return $id > 0 ? $id : null;
        }

        if (!is_string($id) || preg_match('/^\d+$/D', trim($id)) !== 1) {
            return null;
        }

        return (int) trim($id) > 0 ? (int) trim($id) : null;
    }
}
