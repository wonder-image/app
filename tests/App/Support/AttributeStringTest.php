<?php
/** php tests/App/Support/AttributeStringTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\AttributeString;
use Wonder\View\Component;

$stringable = new class implements Stringable {
    public function __toString(): string
    {
        return 'da oggetto';
    }
};

check('P1 true stampa l\'attributo senza valore', fn () =>
    AttributeString::render(['data-wi-save-bar' => true]) === 'data-wi-save-bar'
);

check('P2 false e null omettono l\'attributo, anche il dirty', fn () =>
    AttributeString::render(['data-wi-save-bar' => false, 'data-wi-save-bar-dirty' => null]) === ''
);

check('P3 un array diventa valori uniti da uno spazio, ripuliti e senza vuoti', fn () =>
    AttributeString::render(['class' => ['a', 'b']]) === 'class="a b"'
    && AttributeString::render(['class' => ['a', '', ' b ']]) === 'class="a b"'
    && AttributeString::render(['data-x' => ['0', 'a']]) === 'data-x="0 a"'
);

check('P4 Stringable stampato; array annidati e oggetti nell\'array saltati senza avvisi', function () use ($stringable) {
    $warnings = [];
    set_error_handler(function (int $errno, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });

    try {
        $html = AttributeString::render([
            'title' => $stringable,
            'data-list' => ['a', ['b'], new stdClass(), $stringable],
            'data-object' => new stdClass(),
        ]);
    } finally {
        restore_error_handler();
    }

    return $warnings === [] && $html === 'title="da oggetto" data-list="a da oggetto"';
});

check('P5 escape con ENT_QUOTES ed ENT_SUBSTITUTE', fn () =>
    AttributeString::render(['title' => "<a href=\"x\">'&'</a>"]) === 'title="&lt;a href=&quot;x&quot;&gt;&#039;&amp;&#039;&lt;/a&gt;"'
    && AttributeString::render(['title' => "a\xC3\x28b"]) === "title=\"a\u{FFFD}(b\""
);

check('P6 chiavi riservate ignorate anche con maiuscole o spazi; chiavi ripulite; chiave vuota saltata', fn () =>
    AttributeString::render([
        ' ID ' => 'x',
        'Class' => 'y',
        'method' => 'get',
        'ENCTYPE' => 'z',
        'action' => '/a',
        'onsubmit' => 'return false',
        ' data-wi-save-bar ' => true,
        '  ' => 'vuota',
    ], ['id', 'method', 'enctype', 'action', 'onsubmit', 'class']) === 'data-wi-save-bar'
);

check('P7 array vuoto da stringa vuota e nessuno spazio iniziale', fn () =>
    AttributeString::render([]) === ''
    && AttributeString::render(['a' => false, 'b' => true]) === 'b'
    && AttributeString::render(['' => 'x', 'b' => '1']) === 'b="1"'
);

check('P8 stesso risultato di View\\Component::renderAttributes() su scalari e Stringable', function () use ($stringable) {
    $renderAttributes = new ReflectionMethod(Component::class, 'renderAttributes');
    $component = new Component('test');
    $cases = [
        [],
        ['data-wi-save-bar' => true, 'data-wi-save-bar-dirty' => false, 'hidden' => null],
        ['title' => 'Salva', 'tabindex' => 0, 'data-ratio' => 1.5, 'data-empty' => ''],
        ['title' => "<\"'&>", 'data-object' => $stringable],
        [' data-spaced ' => 'x', '' => 'y'],
    ];

    foreach ($cases as $attributes) {
        if (AttributeString::render($attributes) !== $renderAttributes->invoke($component, $attributes)) {
            return false;
        }
    }

    return true;
});

summary();
