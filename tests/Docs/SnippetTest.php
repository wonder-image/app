<?php
/**
 * Snippet: il codice mostrato riceve le `use` che gli mancano, quello eseguito
 * il `return` sull'ultima istruzione, e i due coincidono per il resto.
 *
 *   php tests/Docs/SnippetTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Docs\Snippet;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\ButtonGroup;

echo "Snippet\n";

check('display() aggiunge solo le use delle classi nominate, in ordine', function () {
    $code = "ButtonGroup::make([Button::make('A')])";
    $display = Snippet::display($code, [Button::class, ButtonGroup::class, \Wonder\Elements\Components\Badge::class]);

    return str_starts_with($display, "use Wonder\\Elements\\Components\\Button;\nuse Wonder\\Elements\\Components\\ButtonGroup;\n\n")
        && !str_contains($display, 'Badge')
        && str_ends_with($display, $code);
});

check('display() non ripete una use già scritta nello snippet', function () {
    $code = "use Wonder\\Elements\\Components\\Button;\n\nButton::make('A')";
    $display = Snippet::display($code, [Button::class]);

    return substr_count($display, 'use Wonder\\Elements\\Components\\Button;') === 1;
});

check('display() ignora i nomi dentro stringhe e commenti', function () {
    $code = "// ButtonGroup qui è un commento\nButton::make('ButtonGroup')";
    $display = Snippet::display($code, [Button::class, ButtonGroup::class]);

    return str_contains($display, 'use Wonder\\Elements\\Components\\Button;')
        && !str_contains($display, 'use Wonder\\Elements\\Components\\ButtonGroup;');
});

check('display() toglie <?php e l\'indentazione comune', function () {
    $code = "<?php\n    Button::make('A')\n        ->variant('danger')\n";
    $display = Snippet::display($code);

    return $display === "Button::make('A')\n    ->variant('danger')";
});

check('executable() mette return davanti a una sola espressione e chiude con ;', function () {
    return Snippet::executable("Button::make('A')\n    ->variant('danger')") === "return Button::make('A')\n    ->variant('danger');";
});

check('executable() mette return solo davanti all\'ultima istruzione', function () {
    $code = "\$items = ['a', 'b'];\nforeach (\$items as \$i) { echo \$i; }\nButtonGroup::make([]);";
    $executable = Snippet::executable($code);

    return $executable === "\$items = ['a', 'b'];\nforeach (\$items as \$i) { echo \$i; }\nreturn ButtonGroup::make([]);";
});

check('executable() lascia stare un return, un echo o un blocco finale', function () {
    return Snippet::executable("return Button::make('A');") === "return Button::make('A');"
        && Snippet::executable("echo Button::make('A');") === "echo Button::make('A');"
        && Snippet::executable("foreach ([1] as \$i) { echo \$i; }") === "foreach ([1] as \$i) { echo \$i; }";
});

check('executable() non si confonde con ; dentro stringhe, array e closure', function () {
    $code = "\$fn = fn () => 'a; b';\n[\n    Button::make('x; y'),\n    \$fn(),\n]";
    $executable = Snippet::executable($code);

    return str_starts_with($executable, "\$fn = fn () => 'a; b';\nreturn [\n");
});

check('executable() di uno snippet vuoto ritorna null', function () {
    return Snippet::executable("   \n") === 'return null;';
});

check('il codice eseguito è quello mostrato, più il return', function () {
    $display = Snippet::display("Button::make('A')", [Button::class]);
    $executable = Snippet::executable($display);

    return str_replace('return ', '', rtrim($executable, ';')) === $display;
});

summary();
