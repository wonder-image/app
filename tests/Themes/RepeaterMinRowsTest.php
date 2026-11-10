<?php
/** php tests/Themes/RepeaterMinRowsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\Support\Repeater as RepeaterSupport;
use Wonder\Support\Prettify\Phone;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Righe minime e prefisso di casa nelle righe.
 *
 * `repeaterMinRows()` è un aiuto a schermo: il contenitore porta il minimo e
 * lo script spegne il bottone che elimina. Il prefisso vuoto parte da quello
 * di casa e conta come default: una riga mai toccata resta vuota.
 */
$render = static function (array $context): string {
    $field = new class($context) {
        public array $schema;

        public function __construct(array $context)
        {
            $this->schema = [
                'id' => 'points',
                'name' => 'points',
                'label' => '',
                'value' => ['row_1' => ['email' => 'a@b.it']],
                'columns' => [RepeaterColumn::key('email')->email()->label('Email')],
                'context' => array_merge(['nested' => true], $context),
            ];
        }
    };

    return (new Repeater)->render($field);
};

check('senza minimo il contenitore non porta l\'attributo né la chiamata', function () use ($render) {
    $html = $render([]);

    return !str_contains($html, 'data-wi-repeater-min=') && !str_contains($html, 'wiRepeaterSyncMin(document');
});

check('con il minimo il contenitore lo porta e lo script si allinea subito', function () use ($render) {
    $html = $render(['min_rows' => 1]);

    return str_contains($html, 'data-wi-repeater-min="1"') && str_contains($html, 'wiRepeaterSyncMin(document.getElementById(');
});

check('repeaterMinRows() scrive il minimo nel context, mai negativo', function () {
    $uno = FormField::key('points')->repeater([RepeaterColumn::key('email')->email()])->repeaterMinRows(1);
    $neg = FormField::key('points')->repeater([RepeaterColumn::key('email')->email()])->repeaterMinRows(-3);

    return ($uno->get('context')['min_rows'] ?? null) === 1 && ($neg->get('context')['min_rows'] ?? null) === 0;
});

check('il prefisso vuoto è un default della riga: quella mai toccata non si salva', function () {
    $field = FormField::key('points')->repeater([
        RepeaterColumn::key('phone_prefix')->phonePrefix(),
        RepeaterColumn::key('phone')->tel(),
    ]);

    return (RepeaterSupport::columnDefaults($field)['phone_prefix'] ?? null) === Phone::$defaultCountryCode;
});

summary();
