<?php

namespace Wonder\App\Scheduler;

use Wonder\Elements\Components\Badge;

final class Presentation
{
    public static function number(mixed $value, float $factor = 1, string $unit = ''): string
    {
        return $value === null ? 'Non disponibile' : number_format((float) $value / $factor, 2, ',', '.').($unit !== '' ? ' '.$unit : '');
    }

    public static function status(string $status): string
    {
        [$label, $color] = match ($status) {
            'success' => ['Riuscita', 'success'], 'failed' => ['Fallita', 'danger'],
            'interrupted' => ['Interrotta', 'warning'], 'running' => ['In esecuzione', 'primary'],
            'pending' => ['In attesa', 'secondary'], 'skipped' => ['Saltata', 'secondary'],
            default => [$status, 'secondary'],
        };
        return Badge::make($label)->variant($color)->render('bootstrap');
    }
}
