<?php
/** php tests/Themes/RepeaterCollapsibleRenderTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

$render = static function (array $value, array $context): string {
    $field = new class($value, $context) {
        public array $schema;

        public function __construct(array $value, array $context)
        {
            $this->schema = [
                'id' => 'people',
                'name' => 'people',
                'label' => 'Referenti',
                'value' => $value,
                'columns' => [
                    RepeaterColumn::key('person_id')->select(['' => 'Nuova persona', '7' => 'Giulia Bianchi'])->columnSpan(6),
                    RepeaterColumn::key('job_title')->text()->columnSpan(5),
                ],
                'context' => array_merge(['nested' => true, 'start_empty' => true], $context),
            ];
        }
    };

    return (new Repeater)->render($field);
};

$markup = static fn (string $html): string => substr($html, 0, (int) strpos($html, '<script'));
$scheda = ['collapsible' => ['title' => ['person_id'], 'subtitle' => ['job_title']], 'collapsible_empty' => 'Nuovo referente'];
$righe = ['row_1' => ['person_id' => '7', 'job_title' => 'Acquisti'], 'row_2' => ['person_id' => '', 'job_title' => '']];
// La riga nel markup, modello escluso.
$riga = static function (string $html, string $key): string {
    $start = (int) strpos($html, 'data-wi-row-key="'.$key.'"');
    $end = strpos($html, 'wi-repeater-row', $start);

    return substr($html, $start, ($end === false ? strlen($html) : $end) - $start);
};

check('senza l\'opzione il markup non cambia', function () use ($render, $markup, $righe) {
    $html = $markup($render($righe, []));

    return !str_contains($html, 'wi-repeater-summary') && !str_contains($html, 'data-wi-collapsible');
});

check('la riga con un titolo nasce chiusa, quella senza aperta', function () use ($render, $markup, $righe, $scheda, $riga) {
    $html = $markup($render($righe, $scheda));
    $chiusa = $riga($html, 'row_1');
    $aperta = $riga($html, 'row_2');

    return str_contains($chiusa, 'aria-expanded="false"') && preg_match('/<fieldset class="[^"]*d-none/', $chiusa) === 1
        && str_contains($aperta, 'aria-expanded="true"') && preg_match('/<fieldset class="[^"]*d-none/', $aperta) === 0;
});

check('chiudere nasconde, non spegne: il fieldset non è mai disabled', function () use ($render, $markup, $righe, $scheda) {
    return preg_match('/<fieldset[^>]*\sdisabled/', $markup($render($righe, $scheda))) === 0;
});

check('il riassunto porta colonne e titolo vuoto, la riga modello è aperta', function () use ($render, $markup, $righe, $scheda, $riga) {
    $html = $markup($render($righe, $scheda));
    $modello = $riga($html, '__ROW_KEY__');

    return str_contains($html, 'data-wi-summary-title="person_id"')
        && str_contains($html, 'data-wi-summary-subtitle="job_title"')
        && str_contains($html, 'data-wi-summary-empty="Nuovo referente"')
        && str_contains($modello, 'aria-expanded="true"');
});

check('dopo un POST le righe tornano aperte', function () use ($render, $markup, $righe, $scheda, $riga) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $html = $markup($render($righe, $scheda));
    unset($_SERVER['REQUEST_METHOD']);

    return str_contains($riga($html, 'row_1'), 'aria-expanded="true"');
});

check('lo script di avvio c\'è solo con l\'opzione', function () use ($render, $righe, $scheda) {
    return str_contains($render($righe, $scheda), "wiRepeaterSummaryInit('people-rows')")
        && !str_contains($render($righe, []), "wiRepeaterSummaryInit('people-rows')");
});

check('repeaterCollapsible() scrive il context e ignora un titolo vuoto', function () {
    $campo = FormField::key('people')->repeater([RepeaterColumn::key('name')->text()])
        ->repeaterCollapsible(['name', ' '], 'job_title')
        ->repeaterCollapsibleEmptyLabel(' Nuovo ');
    $vuoto = FormField::key('people')->repeater([RepeaterColumn::key('name')->text()])->repeaterCollapsible([]);
    $contextOf = static fn (object $field): array => (array) ((new ReflectionProperty($field, 'schema'))->getValue($field)['context'] ?? []);
    $context = $contextOf($campo);

    return ($context['collapsible'] ?? null) === ['title' => ['name'], 'subtitle' => ['job_title']]
        && ($context['collapsible_empty'] ?? null) === 'Nuovo'
        && !isset($contextOf($vuoto)['collapsible']);
});

summary();
