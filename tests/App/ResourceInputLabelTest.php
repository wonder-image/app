<?php
/** php tests/App/ResourceInputLabelTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\FormField;

final class LabelProbeResource extends Resource
{
    public static function path(): string
    {
        return 'app/test/labels';
    }

    public static function labelSchema(): array
    {
        return ['described' => 'Descritto'];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('described')->text(),
            FormField::key('guessed_name')->text(),
            FormField::key('silent')->text()->label(''),
        ];
    }
}

$label = static fn (string $key): string => (string) (LabelProbeResource::getInput($key)->get('label') ?? '');

check('labelSchema dà il nome al campo', fn () => $label('described') === 'Descritto');

check('senza dichiarazione il nome viene dalla chiave', fn () => $label('guessed_name') === 'Guessed Name');

check('un\'etichetta vuota per scelta resta vuota', function () use ($label) {
    // `->label('')` vuol dire "niente etichetta": di solito il campo ha già un
    // titolo sopra, e il nome della colonna ripetuto sotto si legge male.
    return $label('silent') === '';
});

summary();
