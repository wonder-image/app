<?php

namespace Wonder\Data\Fields;

class Image extends File
{
    public string $type = 'image';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->mimeType('image/*');
    }

    public function reset(bool $reset = true): static
    {
        return $this->schema('reset', $reset);
    }

    
    public function resize(array $resize): static
    {
        return $this->schema('resize', $resize);
    }

    public function resizeToWidth(int $width): self
    {
        return $this->resize([['width' => $width]]);
    }

    public function resizeToWidths(array $widths): self
    {
        $resize = array_map(
            static fn (int $width) => ['width' => $width],
            $widths
        );

        return $this->resize($resize);
    }

    public function webp(bool $webp = true): static
    {
        return $this->schema('webp', $webp);
    }

    public function convertToWebp(bool $webp = true): self
    {
        return $this->webp($webp);
    }

    /**
     * Il file resta in un formato solo, qualunque sia quello caricato.
     *
     * Un'icona app arriva volentieri in JPG — è l'export che ti dà il
     * grafico — ma sul sito deve restare un PNG, perché i `<link rel>` nel
     * `<head>` e le misure generate accanto all'originale si aspettano
     * un'estensione sola. Con
     * `->extensions(['png', 'jpg', 'jpeg'])->convertTo('png')` l'upload
     * accetta entrambi e scrive sempre un `.png`: la conversione avviene
     * dopo lo spostamento del file e prima del ridimensionamento, così le
     * misure nascono già nel formato giusto.
     *
     * Formati scrivibili: `png`, `jpg`, `webp`. Convertire verso `jpg`
     * appiattisce la trasparenza, quindi va usato solo dove non serve.
     */
    public function convertTo(string $format): self
    {
        return $this->schema('convert', $format);
    }

    public function quality(int $quality): self
    {
        return $this->schema('quality', $quality);
    }

    /**
     * Il ridimensionamento non si fa al salvataggio: lo farà qualcun altro.
     *
     * Un campo immagine, senza dire niente, prende le misure responsive del
     * sito: caricare venti foto vuol dire generarne centinaia e far aspettare
     * chi sta salvando. Con `deferResize()` l'upload scrive solo l'originale, e
     * chi ha dichiarato il campo si prende la responsabilità di generare le
     * misure dopo (una coda, un comando, un'attività pianificata).
     */
    public function deferResize(bool $deferred = true): self
    {
        return $this->schema('resize_deferred', $deferred);
    }

    public function responsive(?array $widths = null, int $quality = 80): self
    {
        $widths ??= defined('RESPONSIVE_IMAGE_SIZES')
            ? RESPONSIVE_IMAGE_SIZES
            : [120, 480, 620, 960, 1080, 1440, 1920, 2560];

        return $this->resizeToWidths($widths)
            ->quality($quality)
            ->convertToWebp(defined('RESPONSIVE_IMAGE_WEBP') ? (bool) RESPONSIVE_IMAGE_WEBP : true);
    }
}
