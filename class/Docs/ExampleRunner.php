<?php

namespace Wonder\Docs;

use Stringable;
use Throwable;
use Wonder\App\Theme;

/**
 * Esegue lo snippet di un esempio e ne restituisce l'HTML nel tema chiesto.
 *
 * Il codice viene da file del repository (le schede in `docs/components`),
 * mai dalla richiesta: `eval()` qui equivale a un `require`. Il tema attivo
 * viene impostato per la durata dell'esecuzione e poi ripristinato, l'output
 * stampato con `echo` viene catturato e messo prima del valore ritornato.
 */
final class ExampleRunner
{
    public static function render(ComponentDoc $doc, Example $example, string $theme): RenderResult
    {
        $theme = strtolower(trim($theme));
        $display = Snippet::display($example->getCode(), $doc->getUses());
        $code = Snippet::executable($display);
        $previous = Theme::get();

        Theme::set($theme);
        ob_start();

        try {
            $value = self::evaluate($code);
            $output = (string) ob_get_clean();

            return new RenderResult($output.self::stringify($value, $theme), null, $display);
        } catch (Throwable $exception) {
            ob_end_clean();

            return new RenderResult('', $exception::class.': '.$exception->getMessage(), $display);
        } finally {
            Theme::set($previous);
        }
    }

    /** Il codice mostrato nella pagina per questo esempio. */
    public static function display(ComponentDoc $doc, Example $example): string
    {
        return Snippet::display($example->getCode(), $doc->getUses());
    }

    private static function evaluate(string $code): mixed
    {
        $run = static function (string $__code): mixed {
            return eval($__code);
        };

        return $run($code);
    }

    public static function stringify(mixed $value, string $theme): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if (is_array($value)) {
            return implode('', array_map(static fn (mixed $item): string => self::stringify($item, $theme), $value));
        }

        if (is_object($value) && method_exists($value, 'render')) {
            return (string) $value->render($theme);
        }

        if ($value instanceof Stringable || is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
