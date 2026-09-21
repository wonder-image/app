<?php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/** Renderizza il repeater con l'etichetta data. */
$render = static function (string $label): string {
    $field = new class($label) {
        public array $schema;

        public function __construct(string $label)
        {
            $this->schema = [
                'id' => 'values',
                'name' => 'values',
                'label' => $label,
                'value' => null,
                'columns' => [],
                'context' => ['add_label' => 'Aggiungi valore'],
            ];
        }
    };

    return (new Repeater)->render($field);
};

$html = $render('Valori');

check('la riga aggiunta passa dagli inizializzatori della lib', function () use ($html) {
    // Senza questo i campi che diventano un widget (caricamento file, editor,
    // albero, select con ricerca) restano il campo grezzo solo nelle righe
    // nuove: chi guarda la pagina vede due righe diverse tra loro.
    return str_contains($html, "typeof window.setInput === 'function'")
        && str_contains($html, 'window.setInput(row)');
});

check('la lib si inizializza dopo che la riga è nel documento', function () use ($html) {
    $append = strpos($html, 'container.appendChild(fragment)');
    $init = strpos($html, 'window.setInput(row)');

    return $append !== false && $init !== false && $append < $init;
});

check('con un\'etichetta il repeater ha il suo titolo', function () use ($html) {
    return str_contains($html, '<h6>Valori</h6>');
});

check('senza etichetta non resta un titolo vuoto', function () use ($render) {
    return !str_contains($render(''), '<h6>');
});

summary();
