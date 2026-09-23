<?php

namespace Wonder\Themes\Concerns;

use Wonder\App\Support\OptionVisual;

/**
 * L'immagine, l'icona o il colore accanto al nome di un'opzione.
 *
 * Dove l'opzione è HTML (una pillola, una spunta) il segno sta davanti al
 * nome; dove non può esserlo (un `<option>`) viaggia in attributi `data-*`,
 * e lo disegna chi potenzia il select. Richiede `EscapesHtml`.
 */
trait RendersOptionVisual
{
    protected function optionVisual(mixed $option): string
    {
        $visual = OptionVisual::of($option);
        $value = $this->escape($visual['value']);

        return match ($visual['type']) {
            'image' => '<img src="'.$value.'" alt="" class="wi-option-visual rounded-1" width="16" height="16" style="object-fit:cover" loading="lazy"> ',
            'icon' => '<i class="bi '.$value.' wi-option-visual" aria-hidden="true"></i> ',
            'color' => '<i class="bi bi-circle-fill wi-option-visual" style="color:'.$value.'" aria-hidden="true"></i> ',
            default => '',
        };
    }

    protected function optionVisualData(mixed $option): string
    {
        $visual = OptionVisual::of($option);

        return $visual['type'] === ''
            ? ''
            : ' data-wi-'.$visual['type'].'="'.$this->escape($visual['value']).'"';
    }
}
