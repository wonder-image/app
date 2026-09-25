<?php
/** php tests/Themes/InputButtonTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputButton;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Un bottone fra i campi: non posta niente, apre una finestra, e accanto
 * dice quello che la finestra contiene («Filati Nord · 12,00 €»).
 */
$tag = static function (string $html): string {
    preg_match('/<button\b[^>]*>/', $html, $match);

    return $match[0] ?? '';
};

check('è un <button type="button"> senza name', function () use ($tag) {
    $html = FormField::key('cost_button')->button('Costo')->render('bootstrap');

    return str_contains($tag($html), 'type="button"')
        && !str_contains($html, 'name=')
        && str_contains($html, '>Costo</button>');
});

check('di default è outline-secondary, senza id né data-wi-check', function () use ($tag) {
    $html = FormField::key('cost_button')->button('Costo')->render('bootstrap');
    $button = $tag($html);

    return str_contains($button, 'btn-outline-secondary')
        && !str_contains($button, ' id=')
        && !str_contains($html, 'data-wi-check');
});

check('il valore è la didascalia accanto, escapata', function () {
    $html = FormField::key('cost_button')->button('Costo')->value('Filati <Nord> · 12,00 €')->render('bootstrap');

    return str_contains($html, '<div class="d-flex align-items-center gap-2')
        && str_contains($html, '<span class="small text-body-secondary" data-wi-button-caption>Filati &lt;Nord&gt; · 12,00 €</span>');
});

check('senza valore la didascalia è quella di emptyCaption()', function () {
    $html = FormField::key('cost_button')->button('Costo')->emptyCaption('Nessun fornitore')->render('bootstrap');

    return str_contains($html, 'data-wi-button-caption>Nessun fornitore</span>');
});

check('senza valore e senza emptyCaption() la didascalia c\'è, vuota, per il JS', function () {
    $html = FormField::key('cost_button')->button('Costo')->render('bootstrap');

    return str_contains($html, 'data-wi-button-caption></span>');
});

check('opensModal() mette data-bs-toggle e data-bs-target sul bottone', function () use ($tag) {
    $button = $tag(FormField::key('cost_button')->button('Costo')->opensModal('wi-cost-modal')->render('bootstrap'));

    return str_contains($button, 'data-bs-toggle="modal"')
        && str_contains($button, 'data-bs-target="#wi-cost-modal"');
});

check('gli attributi data-* dichiarati con attribute() arrivano al bottone', function () use ($tag) {
    $button = $tag(FormField::key('cost_button')->button('Costo')->attribute('data-wi-supplier-cost="true"')->render('bootstrap'));

    return str_contains($button, 'data-wi-supplier-cost="true"');
});

check('icon(), variant(), outline(false) e size() cambiano il bottone', function () use ($tag) {
    $html = FormField::key('cost_button')->button('Costo')
        ->icon('bi bi-truck')->variant('primary')->outline(false)->size('sm')
        ->render('bootstrap');
    $button = $tag($html);

    return str_contains($button, 'btn-primary')
        && !str_contains($button, 'btn-outline-primary')
        && str_contains($button, 'btn-sm')
        && str_contains($html, '<i class="bi bi-truck"></i> Costo</button>');
});

check('il tipo si raggiunge anche dall\'helper «button»', function () {
    $html = (new FormField('cost_button', 'button'))->label('Costo')->render('bootstrap');

    return FormField::key('cost_button')->button() instanceof InputButton
        && str_contains($html, 'type="button"')
        && str_contains($html, '>Costo</button>');
});

check('sul tema Wonder è lo stesso bottone con la sua didascalia', function () use ($tag) {
    $html = FormField::key('cost_button')->button('Costo')->value('Filati Nord')->opensModal('wi-cost-modal')->render('wonder');

    return str_contains($tag($html), 'type="button"')
        && str_contains($tag($html), 'data-bs-target="#wi-cost-modal"')
        && !str_contains($html, 'name=')
        && str_contains($html, 'data-wi-button-caption>Filati Nord</span>');
});

/** @param array<string, mixed> $context */
$repeater = static function (array $value, array $context = []): string {
    $columns = [
        RepeaterColumn::key('id')->hidden(),
        RepeaterColumn::key('option')->text()->label('Opzione')->columnSpan(3),
        RepeaterColumn::key('cost_summary')->button('Costo')
            ->emptyCaption('Nessun fornitore')->opensModal('wi-cost-modal')->columnSpan(3),
    ];
    $field = new class($value, $context, $columns) {
        public array $schema;

        public function __construct(array $value, array $context, array $columns)
        {
            $this->schema = [
                'id' => 'products',
                'name' => 'products',
                'label' => '',
                'value' => $value,
                'columns' => $columns,
                'context' => array_merge(['nested' => true], $context),
            ];
        }
    };
    $html = (new Repeater)->render($field);

    return substr($html, 0, (int) strpos($html, '<script'));
};

$righe = [
    'row_1' => ['id' => '1', 'option' => 'Rosso', 'cost_summary' => 'Filati Nord · 12,00 €'],
    'row_2' => ['id' => '2', 'option' => 'Blu', 'cost_summary' => 'Lanificio Sud · 9,50 €'],
];

check('come colonna di repeater ogni riga ha la sua didascalia', function () use ($repeater, $righe) {
    $html = $repeater($righe);

    return str_contains($html, 'data-wi-button-caption>Filati Nord · 12,00 €</span>')
        && str_contains($html, 'data-wi-button-caption>Lanificio Sud · 9,50 €</span>')
        // La riga modello, vuota, dice «Nessun fornitore».
        && str_contains($html, 'data-wi-button-caption>Nessun fornitore</span>')
        && substr_count($html, 'data-bs-target="#wi-cost-modal"') === 3
        && !str_contains($html, '[cost_summary]');
});

check('come colonna avanzata il bottone sta nel blocco che si apre', function () use ($repeater, $righe) {
    $html = $repeater($righe, ['advanced' => ['cost_summary']]);
    $blocco = substr($html, (int) strpos($html, 'wi-repeater-advanced d-none"'));

    return str_contains($html, 'wi-repeater-advanced d-none"')
        && str_contains($blocco, 'data-wi-button-caption>Filati Nord · 12,00 €</span>')
        && !str_contains($html, '[cost_summary]');
});

check('in una Card del form prende la sua colonna come ogni campo', function () {
    $html = ResourceFormLayoutRenderer::render(
        (new Form)->components([
            (new Card)->columns(12)->components([
                FormField::key('sku')->text()->label('SKU')->columnSpan(4),
                FormField::key('cost_button')->button('Costo')->emptyCaption('Nessun fornitore')->columnSpan(4),
            ]),
        ])->columns(12)
    );

    // Fuori dal backend i campi prendono il tema di default: conta la
    // colonna, non le classi del wrapper.
    return str_contains($html, '<div class="col-4"><div class="d-flex')
        && str_contains($html, 'data-wi-button-caption>Nessun fornitore</span>');
});

summary();
