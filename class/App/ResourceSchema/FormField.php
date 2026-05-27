<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Support\FormFieldElementFactory;
use Wonder\Elements\Concerns\CanSpanColumn;

class FormField
{
    use CanSpanColumn;

    public string $name;
    protected string $helper = 'text';

    private array $schema = [
        'label' => '',
        'attribute' => '',
        'value' => null,
        'options' => [],
        'search_bar' => false,
        'version' => null,
        'multiple' => false,
        'file' => 'image',
        'uploader' => 'classic',
        'date_min' => null,
        'date_max' => null,
        'time_step' => null,
        'error' => '',
        'prepare' => [],
        'context' => [],
    ];

    public function __construct(
        string $name,
        string $helper = 'text',
    ) {
        $this->name = trim($name);
        $this->helper = trim($helper) !== '' ? trim($helper) : 'text';
    }

    public static function key(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): self
    {
        $this->schema['label'] = $label;

        return $this;
    }

    public function attribute(string $attribute): self
    {
        $attribute = trim($attribute);

        if ($attribute === '') {
            return $this;
        }

        $current = trim((string) ($this->schema['attribute'] ?? ''));
        $this->schema['attribute'] = trim($current.' '.$attribute);

        return $this;
    }

    public function required(bool $required = true): self
    {
        return $required ? $this->attribute('required') : $this;
    }

    public function disabled(bool $disabled = true): self
    {
        return $disabled ? $this->attribute('disabled') : $this;
    }

    public function readonly(bool $readonly = true): self
    {
        return $readonly ? $this->attribute('readonly') : $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->schema['multiple'] = $multiple;

        return $multiple ? $this->attribute('multiple') : $this;
    }

    public function value(mixed $value): self
    {
        $this->schema['value'] = $value;

        return $this;
    }

    public function inputName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function options(array $options): self
    {
        $this->schema['options'] = $options;

        return $this;
    }

    public function searchBar(bool $searchBar = true): self
    {
        $this->schema['search_bar'] = $searchBar;

        return $this;
    }

    public function version(?string $version): self
    {
        $this->schema['version'] = $version;

        return $this;
    }

    public function old(): self
    {
        return $this->version('old');
    }

    public function file(string $file): self
    {
        $this->schema['file'] = trim($file);

        return $this;
    }

    public function uploader(string $uploader = 'classic'): self
    {
        $this->schema['uploader'] = trim($uploader);

        return $this;
    }

    public function dateMin(?string $dateMin): self
    {
        $this->schema['date_min'] = $dateMin;

        return $this;
    }

    public function dateMax(?string $dateMax): self
    {
        $this->schema['date_max'] = $dateMax;

        return $this;
    }

    public function timeStep(?int $timeStep): self
    {
        $this->schema['time_step'] = $timeStep;

        return $this;
    }

    public function error(string $error): self
    {
        $this->schema['error'] = $error;

        return $this;
    }

    public function prepare(string|array $key, mixed $value = true): self
    {
        if (is_array($key)) {
            $this->schema['prepare'] = array_merge(
                (array) ($this->schema['prepare'] ?? []),
                $key
            );

            return $this;
        }

        $this->schema['prepare'][trim($key)] = $value;

        return $this;
    }

    public function context(string|array $key, mixed $value = true): self
    {
        if (is_array($key)) {
            $this->schema['context'] = array_merge(
                (array) ($this->schema['context'] ?? []),
                $key
            );

            return $this;
        }

        $this->schema['context'][trim($key)] = $value;

        return $this;
    }

    public function nested(bool $nested = true): self
    {
        return $this->context('nested', $nested);
    }

    public function repeaterAddLabel(string $label): self
    {
        return $this->context('add_label', trim($label));
    }

    public function repeaterButtonClass(string $class): self
    {
        return $this->context('add_button_class', trim($class));
    }

    public function repeaterDeleteTitle(string $title): self
    {
        return $this->context('delete_modal_title', trim($title));
    }

    public function repeaterDeleteText(string $text): self
    {
        return $this->context('delete_modal_text', trim($text));
    }

    public function repeaterDeleteCancelLabel(string $label): self
    {
        return $this->context('delete_modal_cancel_label', trim($label));
    }

    public function repeaterDeleteConfirmLabel(string $label): self
    {
        return $this->context('delete_modal_confirm_label', trim($label));
    }

    public function repeaterDeleteConfirmClass(string $class): self
    {
        return $this->context('delete_modal_confirm_class', trim($class));
    }

    public function repeaterSortable(bool $sortable = true): self
    {
        return $this->context('sortable', $sortable);
    }

    public function relation(object $relation): self
    {
        return $this->context('relation', $relation);
    }

    public function storeAs(string $name): self
    {
        return $this->prepare('name', $name);
    }

    public function maxSize(int $size): self
    {
        return $this->prepare('max_size', $size);
    }

    public function maxFile(int $count): self
    {
        return $this->prepare('max_file', $count);
    }

    public function extensions(array $extensions): self
    {
        return $this->prepare('extensions', $extensions);
    }

    public function get(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->schema;
        }

        if ($key === 'helper') {
            return $this->helper;
        }

        return $this->schema[$key] ?? null;
    }

    public function text(): self
    {
        $this->helper = 'text';

        return $this;
    }

    public function hidden(): self
    {
        $this->helper = 'hidden';

        return $this;
    }

    public function textDate(): self
    {
        $this->helper = 'textDate';

        return $this;
    }

    public function textDatetime(): self
    {
        $this->helper = 'textDatetime';

        return $this;
    }

    public function dateInput(?string $dateMin = null, ?string $dateMax = null): self
    {
        $this->helper = 'dateInput';
        $this->dateMin($dateMin);
        $this->dateMax($dateMax);

        return $this;
    }

    public function timeInput(?int $step = 900): self
    {
        $this->helper = 'timeInput';
        $this->timeStep($step);

        return $this;
    }

    public function dateRange(?string $dateMin = null, ?string $dateMax = null): self
    {
        $this->helper = 'dateRange';
        $this->dateMin($dateMin);
        $this->dateMax($dateMax);

        return $this;
    }

    public function color(): self
    {
        $this->helper = 'color';

        return $this;
    }

    public function email(): self
    {
        $this->helper = 'email';

        return $this;
    }

    public function number(): self
    {
        $this->helper = 'number';

        return $this;
    }

    public function price(): self
    {
        $this->helper = 'price';

        return $this;
    }

    public function percentige(): self
    {
        $this->helper = 'percentige';

        return $this;
    }

    public function password(): self
    {
        $this->helper = 'password';

        return $this;
    }

    public function tel(): self
    {
        $this->helper = 'phone';

        return $this;
    }

    public function phone(): self
    {
        return $this->tel();
    }

    public function url(): self
    {
        $this->helper = 'url';

        return $this;
    }

    public function textarea(?string $version = null): self
    {
        $this->helper = 'textarea';
        $this->version($version);

        return $this;
    }

    public function select(array $options = [], ?string $version = null): self
    {
        $this->helper = 'select';
        $this->options($options);
        $this->version($version);

        return $this;
    }

    public function radio(array $options = [], bool $searchBar = false): self
    {
        $this->helper = 'radio';
        $this->options($options);
        $this->searchBar($searchBar);

        return $this;
    }

    public function selectSearch(array $options = [], bool $multiple = false, ?string $version = null): self
    {
        $this->helper = 'selectSearch';
        $this->options($options);
        $this->multiple($multiple);
        $this->version($version);

        return $this;
    }

    public function checkbox(): self
    {
        $this->helper = 'checkbox';

        return $this;
    }

    public function inputFile(string $file = 'image'): self
    {
        $this->helper = 'inputFile';
        $this->file($file);

        return $this;
    }

    public function inputFileDragDrop(string $file = 'image', string $uploader = 'classic'): self
    {
        $this->helper = 'inputFileDragDrop';
        $this->file($file);
        $this->uploader($uploader);

        return $this;
    }

    public function country(?string $stateField = null): self
    {
        $this->helper = 'inputCountry';

        if ($stateField !== null && trim($stateField) !== '') {
            $this->context('state_field', trim($stateField));
        }

        return $this;
    }

    public function states(?string $country = null): self
    {
        $this->helper = 'inputStates';

        if ($country !== null && trim($country) !== '') {
            $this->context('country', trim($country));
        }

        return $this;
    }

    public function phonePrefix(): self
    {
        $this->helper = 'inputPhonePrefix';

        return $this;
    }

    public function repeater(array $columns = []): self
    {
        $this->helper = 'inputRepeater';

        if ($columns !== []) {
            $this->context('columns', $columns);
        }

        return $this;
    }

    /**
     * Text input + bottone "GENERA" (Wonder\Elements\Form\Components\TextGenerator).
     *
     * @param string|null $callback   Funzione JS chiamata al click (default `generateCode`).
     * @param string|null $buttonLabel Label del bottone (default `GENERA`).
     */
    public function textGenerator(?string $callback = null, ?string $buttonLabel = null): self
    {
        $this->helper = 'textGenerator';

        if ($callback !== null && trim($callback) !== '') {
            $this->context('callback', trim($callback));
        }

        if ($buttonLabel !== null && trim($buttonLabel) !== '') {
            $this->context('button_label', trim($buttonLabel));
        }

        return $this;
    }

    /**
     * Lista checkbox/radio ad albero jsTree
     * (Wonder\Elements\Form\Components\CheckTree).
     *
     * @param array  $options    Opzioni (può contenere `child` per sotto-livelli).
     * @param bool   $searchBar  Aggiunge la barra di ricerca testuale.
     * @param string $inputType  'checkbox' (default) o 'radio'.
     */
    public function checkTree(array $options = [], bool $searchBar = false, string $inputType = 'checkbox'): self
    {
        $this->helper = 'checkTree';
        $this->options($options);
        $this->searchBar($searchBar);

        return $this->context('input_type', $inputType === 'radio' ? 'radio' : 'checkbox');
    }

    /**
     * Check (checkbox/radio) con risultati caricati via AJAX
     * (Wonder\Elements\Form\Components\DynamicCheck).
     */
    public function dynamicCheck(string $url, string $inputType = 'checkbox'): self
    {
        $this->helper = 'dynamicCheck';

        return $this->context([
            'url' => trim($url),
            'input_type' => $inputType === 'radio' ? 'radio' : 'checkbox',
        ]);
    }

    /**
     * Toggle Si/No a 3 stati (null/true/false)
     * (Wonder\Elements\Form\Components\CheckBoolean).
     *
     * @param array $values Tripla [valueNull, valueTrue, valueFalse] dei valori
     *                      effettivamente postati dal form. Default `['', 'true', 'false']`.
     */
    public function checkBoolean(array $values = ['', 'true', 'false'], ?string $trueLabel = null, ?string $falseLabel = null): self
    {
        $this->helper = 'checkBoolean';

        $this->context('boolean_values', array_pad($values, 3, ''));

        if ($trueLabel !== null && trim($trueLabel) !== '') {
            $this->context('true_label', trim($trueLabel));
        }

        if ($falseLabel !== null && trim($falseLabel) !== '') {
            $this->context('false_label', trim($falseLabel));
        }

        return $this;
    }

    /**
     * Google reCAPTCHA v2 — "Casella di controllo: Non sono un robot".
     *
     * Renderizza il widget `g-recaptcha` con sitekey letta da
     * `Credentials::api()` e gli input hidden richiesti dalla
     * verifica server-side `verifyRecaptcha`. È il pendant DSL di
     * `Wonder\Plugin\Custom\Input\reCAPTCHA` / `inputRecaptcha()`.
     *
     * @param string|null $action `submit` (default), o action logica
     *                            validata server-side.
     * @param string|null $theme  `light` (default) o `dark`.
     * @param string|null $size   `normal` (default) o `compact`.
     */
    public function recaptcha(?string $action = null, ?string $theme = null, ?string $size = null): self
    {
        $this->helper = 'recaptcha';

        if ($action !== null && trim($action) !== '') {
            $this->context('recaptcha_action', trim($action));
        }

        if ($theme !== null && trim($theme) !== '') {
            $this->context('recaptcha_theme', trim($theme));
        }

        if ($size !== null && trim($size) !== '') {
            $this->context('recaptcha_size', trim($size));
        }

        return $this;
    }

    /**
     * Google Places address con autocomplete + breakdown nascosti
     * (Wonder\Elements\Form\Components\GoogleAddress).
     *
     * @param array       $restriction Restrizioni Google Places (es. `['country' => 'it']`).
     * @param string|null $alias       Prefisso per i 6 hidden field. `null` (default) usa il `name`
     *                                  del campo; passa una stringa esplicita se vuoi
     *                                  più indirizzi nello stesso form.
     */
    public function googleAddress(array $restriction = [], ?string $alias = null): self
    {
        $this->helper = 'googleAddress';

        if ($restriction !== []) {
            $this->context('restriction', $restriction);
        }

        if ($alias !== null && trim($alias) !== '') {
            $this->context('alias', trim($alias));
        }

        return $this;
    }

    /**
     * Renderizza il campo come HTML del tema attivo (o di quello esplicito).
     *
     * Unica strada: `FormFieldElementFactory::make()` mappa il `helper`
     * a un Element neutro, che il `Wonder\Themes\Resolver` rende col
     * tema corrente. Se l'helper non è gestito dalla Factory si lancia
     * un'eccezione esplicita — niente fallback a funzioni procedurali.
     */
    public function render(?string $theme = null): string
    {
        $rendered = FormFieldElementFactory::render($this, $theme);

        if ($rendered === null) {
            throw new RuntimeException(
                "Helper form non supportato: {$this->helper}. "
                ."Aggiungi la mappatura in Wonder\\App\\Support\\FormFieldElementFactory::make()."
            );
        }

        return $rendered;
    }
}
