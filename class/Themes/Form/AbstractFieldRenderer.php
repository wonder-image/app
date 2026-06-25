<?php

namespace Wonder\Themes\Form;

use Wonder\Concerns\HasSchema;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\EscapesHtml;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\HasIdentifier;
use Wonder\Themes\Contracts\Renderer;

/**
 * Base condivisa per i renderer di campi form di qualsiasi tema
 * (`Wonder`, `Bootstrap`, eventuali futuri).
 *
 * Estrae la logica IDENTICA tra `Themes\Wonder\Form\Field` e
 * `Themes\Bootstrap\Form\Field` (lettura schema, label con `*` per
 * required, errore/validazione, valore "compilato"). Le parti
 * theme-specifiche (HTML di label, errore, classe input, eventuali
 * wrapping container) restano nei subclassi.
 *
 * I subclassi devono:
 * - implementare `renderInput(): string` (HTML del campo vero e proprio)
 * - opzionalmente overridare `renderField(string $input): string` come
 *   hook di wrapping (default: nessun wrapping; Bootstrap lo usa per
 *   il pattern `form-floating`)
 * - implementare i propri `renderLabel()`, `renderError()`,
 *   `inputClass()` con il markup del tema (classi `wi-*` per Wonder,
 *   `form-control`/`is-invalid` per Bootstrap)
 *
 * NON estende i `Themes\{Wonder,Bootstrap}\Component` perché quei
 * Component theme-specifici sono "container" (con `renderComponents()`),
 * mentre un Field renderizza un singolo input. Le funzionalità che ci
 * servono (schema, attributi, escape HTML, identificatore, span colonna)
 * sono incluse direttamente come trait qui.
 */
abstract class AbstractFieldRenderer implements Renderer
{
    use HasSchema;           // $schema array + helper schema()/getSchema()
    use HasAttributes;       // attr() + renderAttributes() per HTML attrs
    use HasIdentifier;       // $id univoco per coppia <label for>/<input id>
    use EscapesHtml;         // escape() htmlspecialchars wrapper
    use CanSpanColumn;       // columnSpan() del trait Bootstrap (riusato qui
                             // perché è theme-agnostico anche se vive lì
                             // per ragioni storiche)

    /**
     * Entry point standard del rendering. Il Resolver chiama
     * `$renderer->render($elementInstance)` passando l'oggetto Element
     * (es. un `Wonder\Elements\Form\Components\InputText`); estraiamo
     * lo schema e poi delega ai due hook:
     *
     *   1. `renderInput()`   — markup dell'input vero (astratto)
     *   2. `renderField()`   — wrapping opzionale (default: passa-attraverso)
     */
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput());
    }

    abstract public function renderInput(): string;

    /**
     * Hook di wrapping. Default = identità (nessun container).
     * I temi possono overridare per wrappare l'input in
     * `<div class="form-floating">…</div>`, oppure aggiungere
     * pre/post markup, ecc.
     */
    protected function renderField(string $input): string
    {
        return $input;
    }

    /**
     * `true` se lo schema contiene un errore di validazione non vuoto.
     * Usato dai renderer per applicare classi `is-invalid` / `input-error`
     * e mostrare il messaggio.
     */
    protected function hasError(): bool
    {
        return $this->errorMessage() !== '';
    }

    protected function errorMessage(): string
    {
        return trim((string) ($this->schema['error'] ?? ''));
    }

    /**
     * `true` se il campo ha un valore "non vuoto". Distingue stringa
     * vuota, null e array vuoto da valore presente. Wonder lo usa per
     * applicare la classe `compiled` (campo già riempito).
     */
    protected function hasValue(): bool
    {
        $value = $this->schema['value'] ?? null;

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    }

    /**
     * Label finale come testo: il `label` dello schema più un `*`
     * suffisso se l'attributo `required` è impostato. NON include
     * il markup: ogni tema lo wrappa nel suo `<label>` con classi
     * proprie.
     */
    protected function resolvedLabel(): string
    {
        $label = trim((string) ($this->schema['label'] ?? ''));

        if (!empty($this->schema['attributes']['required'])) {
            $label .= '*';
        }

        return $label;
    }

    /**
     * `true` se il campo è in modalità "no floating label". L'opt-in
     * arriva da `Field::noFloating()` (per singolo input) oppure è
     * propagato da `Form::noFloating()` ai children prima del render
     * (vedi `Themes/{Wonder,Bootstrap}/Form/Form::render()`).
     *
     * Wonder: aggiunge la classe `wi-nf` al `.wi-input-container`.
     * Bootstrap: salta il wrap `<div class="form-floating">`.
     */
    protected function isNoFloating(): bool
    {
        return (bool) ($this->schema['no_floating'] ?? false);
    }
}
