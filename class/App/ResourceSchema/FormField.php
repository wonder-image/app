<?php

namespace Wonder\App\ResourceSchema;

/**
 * Facade storica del DSL form: espone i 45 type-helper (`text()`,
 * `password()`, `file()`, `select()`, `acceptDocument()`, ...) come
 * metodi d'istanza che mutano `$this` e tornano `self`.
 *
 * La "macchina" condivisa (label, attribute, prepare, context, render,
 * __toString) vive in `Wonder\App\ResourceSchema\Input` — `FormField`
 * estende `Input` per ereditarla.
 *
 * **Direzione del refactor**: gradualmente i type-helper vengono migrati
 * in classi dedicate sotto `Wonder\App\ResourceSchema\Inputs\` (es.
 * `InputText`, `InputPassword`). Quando tutti i tipi saranno migrati,
 * `FormField::key()` diventerà un dispatcher che ritorna direttamente
 * la `Input*` corretta. Per ora i due mondi convivono: entrambi
 * estendono `Input` e sono accettati dal `FormFieldElementFactory`.
 */
class FormField extends Input
{
    public function __construct(
        string $name,
        string $helper = 'text',
    ) {
        parent::__construct($name);
        $this->helper = trim($helper) !== '' ? trim($helper) : 'text';
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

    /**
     * Setters della password policy.
     *
     * Le regole finiscono in `prepare['password_rules']` (un singolo array
     * assoc): `Resource::prepareFormatFromInput()` le copia in
     * `format['password_rules']`, da dove le legge sia il render (via
     * `FormFieldElementFactory::passwordElement()` che le propaga
     * all'`InputPassword` Element) sia la validazione server-side dentro
     * `formToArray()`, tramite `PasswordPolicyValidator`.
     *
     * Le stesse API sono mirror di quelle su `Wonder\Data\Fields\Password`,
     * così un Model che dichiara `Field::key('password')->password()
     * ->minLength(8)` ottiene la stessa policy senza dover ripassare dal
     * FormField del Resource.
     */
    public function minLength(int $length): self
    {
        return $this->passwordRuleSet('min_length', max(0, $length));
    }

    public function requireUppercase(bool $required = true): self
    {
        return $this->passwordRuleSet('uppercase', $required);
    }

    public function requireLowercase(bool $required = true): self
    {
        return $this->passwordRuleSet('lowercase', $required);
    }

    public function requireNumber(bool $required = true): self
    {
        return $this->passwordRuleSet('number', $required);
    }

    public function requireSpecial(bool $required = true): self
    {
        return $this->passwordRuleSet('special', $required);
    }

    private function passwordRuleSet(string $key, mixed $value): self
    {
        $rules = (array) (($this->schema['prepare']['password_rules'] ?? []) ?: []);

        if ($value === false || $value === 0 || $value === '0') {
            unset($rules[$key]);
        } else {
            $rules[$key] = $value;
        }

        return $this->prepare('password_rules', $rules);
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

    public function bool(?string $version = null): self
    {

        return $this->select([ 'true' => 'Sì', 'false' => 'No' ], $version)->value('true');

    }

    public function position(?string $version = null): self
    {
        $options = [];
        for ($i=0; $i < 11; $i++) { $options[$i] = $i; }

        return $this->select($options, $version);

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

    /**
     * Upload "classic" (form-control + lista file ammessi/max/peso).
     * Il parametro `$accept` identifica la *categoria* di file accettata
     * (es. `image`, `pdf`, `video`, `font`, `media`): il renderer la
     * traduce in attributo `accept="..."` e in label informativa.
     */
    public function file(string $accept = 'image'): self
    {
        $this->helper = 'inputFile';
        $this->schema['file'] = trim($accept);

        return $this;
    }

    /**
     * Upload drag&drop (Filepond). `$accept` come in {@see inputFile()};
     * `$uploader` è la strategia di upload lato client (`classic` o un
     * uploader registrato).
     */
    public function fileDragDrop(string $accept = 'image', string $uploader = 'classic'): self
    {
        $this->helper = 'inputFileDragDrop';
        $this->schema['file'] = trim($accept);
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
     * Checkbox di accettazione di un documento legale (privacy, terms, ...).
     *
     * Coerente con il resto del DSL: `FormField::key('accept_privacy_policy')
     * ->acceptDocument('privacy_policy')->required()`. Il `name` del field
     * resta quello passato a `::key()`: deve coincidere con `accept_<type>`
     * perché `user()` (app/function/user/user.php) e `ConsentService` cercano
     * proprio quel prefisso nel POST per registrare il consenso in
     * `consent_events` / `user_consent_state`.
     *
     * I dati del documento (id, label HTML, ...) vengono risolti al render
     * in `FormFieldElementFactory::resolveLegalDocument()` per la lingua
     * corrente, leggendo `context.document_type`.
     */
    public function acceptDocument(string $type): self
    {
        $type = strtolower(trim($type));
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        $this->helper = 'inputAcceptDocument';
        $this->context('document_type', $type);

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
}
