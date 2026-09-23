<?php

namespace Wonder\App\Support;

/**
 * Quello che si vede accanto al nome di un'opzione: un'immagine, un'icona o
 * un colore.
 *
 * Un'opzione di select o di un gruppo di spunte può essere una stringa (il
 * nome) o un array con `name`; qui si leggono anche `image`, `icon` e
 * `color`. Se ce n'è più d'uno vince l'immagine, poi l'icona, poi il colore:
 * chi ha caricato un file ha scelto quello, e l'icona di una raccolta è il
 * ripiego.
 *
 * I valori arrivano spesso dal database, cioè da chi compila un'anagrafica:
 * finiscono in un attributo `class`, `style` o `src`, e qui si accettano solo
 * nelle forme che non possono uscire da lì.
 */
final class OptionVisual
{
    /** Il nome di un'icona Bootstrap: `bi-star`, `bi-droplet-half`. */
    public const ICON_PATTERN = '/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @return array{type: string, value: string} `type` vuoto quando non c'è
     *                                            niente da mostrare
     */
    public static function of(mixed $option): array
    {
        if (!is_array($option)) {
            return ['type' => '', 'value' => ''];
        }

        $image = self::image((string) ($option['image'] ?? ''));

        if ($image !== '') {
            return ['type' => 'image', 'value' => $image];
        }

        $icon = self::icon((string) ($option['icon'] ?? ''));

        if ($icon !== '') {
            return ['type' => 'icon', 'value' => $icon];
        }

        $color = self::color((string) ($option['color'] ?? ''));

        if ($color !== '') {
            return ['type' => 'color', 'value' => $color];
        }

        return ['type' => '', 'value' => ''];
    }

    /** Il nome dell'icona, o vuoto se non è un nome di icona. */
    public static function icon(string $icon): string
    {
        $icon = strtolower(trim($icon));

        if ($icon !== '' && !str_starts_with($icon, 'bi-')) {
            $icon = 'bi-'.$icon;
        }

        return preg_match(self::ICON_PATTERN, $icon) === 1 ? $icon : '';
    }

    /** Un colore esadecimale, o vuoto: è l'unica forma che scrive l'input colore. */
    public static function color(string $color): string
    {
        $color = trim($color);

        return preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color) === 1 ? $color : '';
    }

    /**
     * L'indirizzo di un'immagine, o vuoto.
     *
     * Solo percorsi e `http(s)`: un altro schema (`javascript:`, `data:`) in
     * un `src` è un modo di far eseguire qualcosa a chi guarda la pagina.
     */
    public static function image(string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\s"\'<>]/', $url) === 1) {
            return '';
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1 && preg_match('#^https?://#i', $url) !== 1) {
            return '';
        }

        return $url;
    }
}
