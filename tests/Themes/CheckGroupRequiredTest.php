<?php
/**
 * CheckGroup obbligatorio: vale «almeno una», quindi `required` non va su
 * ogni casella (il browser le pretenderebbe tutte, come le pagine del
 * popup) ma sul contenitore, con la classe `wi-{type}-required`.
 *
 *   php tests/Themes/CheckGroupRequiredTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\Elements\Form\Components\CheckGroup;
use Wonder\Themes\Support\PageAssets;

echo "CheckGroupRequired\n";

/** I tag `<input>` del tipo dato. */
function inputsOfType(string $html, string $type): array
{
    preg_match_all('/<input\b[^>]*\btype="'.$type.'"[^>]*>/', $html, $matches);

    return $matches[0];
}

function anyRequired(array $inputs): bool
{
    foreach ($inputs as $input) {
        if (preg_match('/\srequired(?=[\s>=])/', $input) === 1) {
            return true;
        }
    }

    return false;
}

$group = static fn (string $type = 'checkbox', bool $pills = false): CheckGroup => (new CheckGroup('pages'))
    ->label('Pagine')
    ->inputType($type)
    ->options(['home' => 'Home', 'shop' => 'Negozio', 'contact' => 'Contatti'])
    ->pills($pills)
    ->required();

foreach (['bootstrap', 'wonder'] as $theme) {
    foreach (['lista' => false, 'pillole' => true] as $mode => $pills) {
        foreach (['checkbox', 'radio'] as $type) {
            check("{$theme}, {$mode}, {$type}: nessuna casella required, il contenitore porta wi-{$type}-required", function () use ($group, $theme, $type, $pills) {
                PageAssets::reset();
                $html = $group($type, $pills)->render($theme);
                $inputs = inputsOfType($html, $type);

                return count($inputs) === 3
                    && !anyRequired($inputs)
                    && str_contains($html, "wi-{$type}-required");
            });
        }
    }

    check("{$theme}: senza required() niente classe sul contenitore", function () use ($theme) {
        PageAssets::reset();
        $html = (new CheckGroup('pages'))->label('Pagine')->options(['a' => 'A', 'b' => 'B'])->render($theme);

        return !str_contains($html, 'wi-checkbox-required') && !anyRequired(inputsOfType($html, 'checkbox'));
    });

    check("{$theme}: l'etichetta del gruppo obbligatorio tiene l'asterisco", function () use ($group, $theme) {
        PageAssets::reset();

        return str_contains($group()->render($theme), 'Pagine*');
    });

    check("{$theme}: gli altri attributi restano sulle caselle", function () use ($theme) {
        PageAssets::reset();
        $html = (new CheckGroup('pages'))->options(['a' => 'A', 'b' => 'B'])->required()->disabled()->render($theme);
        $inputs = inputsOfType($html, 'checkbox');

        return count($inputs) === 2
            && count(array_filter($inputs, static fn (string $input): bool => str_contains($input, ' disabled'))) === 2
            && !anyRequired($inputs);
    });
}

check('bootstrap: anche le caselle figlie non sono required', function () {
    $html = (new CheckGroup('tags'))
        ->options(['1' => ['name' => 'Padre', 'child' => ['2' => 'Figlio']]])
        ->required()
        ->render('bootstrap');
    $inputs = inputsOfType($html, 'checkbox');

    return count($inputs) === 2 && !anyRequired($inputs);
});

check('FormField checkbox()->required() (campo pagine del popup): niente required sulle caselle', function () {
    $html = FormField::key('pages')
        ->checkbox()
        ->options(['all' => 'Tutte le pagine', 'home' => 'Home', 'shop' => 'Negozio'])
        ->searchBar()
        ->required()
        ->render('bootstrap');
    $inputs = inputsOfType($html, 'checkbox');

    return count($inputs) === 3
        && !anyRequired($inputs)
        && str_contains($html, 'wi-container-checkbox wi-checkbox-required');
});

summary();
