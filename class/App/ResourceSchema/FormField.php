<?php

namespace Wonder\App\ResourceSchema;

use Wonder\App\ResourceSchema\Inputs\Concerns\NormalizesExtensions;
use Wonder\App\ResourceSchema\Inputs\Concerns\WritesNumberConfig;
use Wonder\App\ResourceSchema\Inputs\Concerns\WritesPasswordRules;
use Wonder\App\ResourceSchema\Inputs\InputAcceptDocument;
use Wonder\App\ResourceSchema\Inputs\InputCheckBoolean;
use Wonder\App\ResourceSchema\Inputs\InputCheckTree;
use Wonder\App\ResourceSchema\Inputs\InputCheckbox;
use Wonder\App\ResourceSchema\Inputs\InputColor;
use Wonder\App\ResourceSchema\Inputs\InputCountry;
use Wonder\App\ResourceSchema\Inputs\InputDate;
use Wonder\App\ResourceSchema\Inputs\InputDateRange;
use Wonder\App\ResourceSchema\Inputs\InputDynamicCheck;
use Wonder\App\ResourceSchema\Inputs\InputEmail;
use Wonder\App\ResourceSchema\Inputs\InputFile;
use Wonder\App\ResourceSchema\Inputs\InputFileDragDrop;
use Wonder\App\ResourceSchema\Inputs\InputGoogleAddress;
use Wonder\App\ResourceSchema\Inputs\InputHidden;
use Wonder\App\ResourceSchema\Inputs\InputNumber;
use Wonder\App\ResourceSchema\Inputs\InputPassword;
use Wonder\App\ResourceSchema\Inputs\InputPercentige;
use Wonder\App\ResourceSchema\Inputs\InputPhone;
use Wonder\App\ResourceSchema\Inputs\InputPhonePrefix;
use Wonder\App\ResourceSchema\Inputs\InputPrice;
use Wonder\App\ResourceSchema\Inputs\InputRadio;
use Wonder\App\ResourceSchema\Inputs\InputReCaptcha;
use Wonder\App\ResourceSchema\Inputs\InputRepeater;
use Wonder\App\ResourceSchema\Inputs\InputSearchRadio;
use Wonder\App\ResourceSchema\Inputs\InputSearchText;
use Wonder\App\ResourceSchema\Inputs\InputSelect;
use Wonder\App\ResourceSchema\Inputs\InputSelectSearch;
use Wonder\App\ResourceSchema\Inputs\InputStates;
use Wonder\App\ResourceSchema\Inputs\InputText;
use Wonder\App\ResourceSchema\Inputs\InputTextDate;
use Wonder\App\ResourceSchema\Inputs\InputTextDatetime;
use Wonder\App\ResourceSchema\Inputs\InputTextGenerator;
use Wonder\App\ResourceSchema\Inputs\InputTextList;
use Wonder\App\ResourceSchema\Inputs\InputTextarea;
use Wonder\App\ResourceSchema\Inputs\InputTime;
use Wonder\App\ResourceSchema\Inputs\InputUrl;

/**
 * Facade retro-compatibile del DSL form.
 *
 * Espone i 45 type-helper (`text()`, `password()`, `file()`, `select()`,
 * `acceptDocument()`, ...) come punto d'ingresso unico:
 * `FormField::key('nome')->tipo()->modificatori()`. Ogni type-helper NON muta
 * più `$this` — costruisce e ritorna l'istanza `Inputs\Input*` del tipo scelto
 * (vedi {@see Input::morphInto()}), che espone i soli modificatori sensati per
 * quel tipo. Da lì in poi l'autocomplete smette di suggerire `options()` su un
 * numero o `maxFile()` su una password.
 *
 * Chi vuole partire già tipizzato può istanziare direttamente la classe:
 * `InputNumber::key('prezzo')->decimals(2)` equivale a
 * `FormField::key('prezzo')->number()->decimals(2)`.
 *
 * ## Shim `@deprecated`
 *
 * I modificatori type-specific vivono ora sulle classi tipizzate, ma restano
 * qui come delegatori marcati `@deprecated`: servono alle catene che chiamano
 * un modificatore *prima* del type-helper (`->options([...])->select()`), un
 * ordine ancora diffuso nei siti e nei moduli `wonder-image/<slug>`. Lo
 * schema accumulato sopravvive al morph, quindi quelle catene rendono HTML
 * identico. Vanno rimossi solo quando nessun call site esterno li usa più:
 * la forma da preferire è sempre type-helper prima, modificatori poi.
 *
 * `FormInput` e `RepeaterColumn` estendono questa classe e ne ereditano
 * l'intero comportamento.
 */
class FormField extends Input
{
    use NormalizesExtensions;
    use WritesNumberConfig;
    use WritesPasswordRules;

    public function __construct(
        string $name,
        string $helper = 'text',
    ) {
        parent::__construct($name);
        $this->helper = trim($helper) !== '' ? trim($helper) : 'text';
    }

    public function text(): InputText
    {
        return $this->morphInto(InputText::class);
    }

    public function hidden(): InputHidden
    {
        return $this->morphInto(InputHidden::class);
    }

    public function textDate(): InputTextDate
    {
        return $this->morphInto(InputTextDate::class);
    }

    public function textDatetime(): InputTextDatetime
    {
        return $this->morphInto(InputTextDatetime::class);
    }

    public function dateInput(?string $dateMin = null, ?string $dateMax = null): InputDate
    {
        return $this->morphInto(InputDate::class)
            ->dateMin($dateMin)
            ->dateMax($dateMax);
    }

    public function timeInput(?int $step = 900): InputTime
    {
        return $this->morphInto(InputTime::class)->timeStep($step);
    }

    public function dateRange(?string $dateMin = null, ?string $dateMax = null): InputDateRange
    {
        return $this->morphInto(InputDateRange::class)
            ->dateMin($dateMin)
            ->dateMax($dateMax);
    }

    public function color(): InputColor
    {
        return $this->morphInto(InputColor::class);
    }

    public function email(): InputEmail
    {
        return $this->morphInto(InputEmail::class);
    }

    public function number(): InputNumber
    {
        return $this->morphInto(InputNumber::class);
    }

    public function price(): InputPrice
    {
        return $this->morphInto(InputPrice::class);
    }

    public function percentige(): InputPercentige
    {
        return $this->morphInto(InputPercentige::class);
    }

    public function password(): InputPassword
    {
        return $this->morphInto(InputPassword::class);
    }

    public function tel(): InputPhone
    {
        return $this->morphInto(InputPhone::class);
    }

    public function phone(): InputPhone
    {
        return $this->tel();
    }

    public function url(): InputUrl
    {
        return $this->morphInto(InputUrl::class);
    }

    public function textarea(?string $version = null): InputTextarea
    {
        return $this->morphInto(InputTextarea::class)->version($version);
    }

    public function select(array $options = [], ?string $version = null): InputSelect
    {
        return $this->morphInto(InputSelect::class)
            ->options($options)
            ->version($version);
    }

    public function bool(?string $version = null): InputSelect
    {
        return $this->select(['true' => 'Sì', 'false' => 'No'], $version)->value('true');
    }

    public function position(?string $version = null): InputSelect
    {
        $options = [];

        for ($i = 0; $i < 11; $i++) {
            $options[$i] = $i;
        }

        return $this->select($options, $version);
    }

    public function radio(array $options = [], bool $searchBar = false): InputRadio
    {
        return $this->morphInto(InputRadio::class)
            ->options($options)
            ->searchBar($searchBar);
    }

    public function selectSearch(array $options = [], bool $multiple = false, ?string $version = null): InputSelectSearch
    {
        return $this->morphInto(InputSelectSearch::class)
            ->options($options)
            ->multiple($multiple)
            ->version($version);
    }

    /**
     * Combobox "text + list": campo di testo con dropdown filtrabile su una
     * lista *statica* di opzioni (`[value => label]`). Sul tema Wonder la
     * selezione popola l'input con la label e un radio nascosto trasporta il
     * value effettivo; sul tema Bootstrap degrada a un select ricercabile.
     * Da preferire a `select()` quando le opzioni sono molte e serve la
     * ricerca lato client senza chiamate remote (vedi `searchText()` per la
     * ricerca via AJAX).
     */
    public function textList(array $options = [], ?string $version = null): InputTextList
    {
        return $this->morphInto(InputTextList::class)
            ->options($options)
            ->version($version);
    }

    /**
     * Ricerca remota a testo libero: input che popola una dropdown via AJAX
     * dall'endpoint `$url`. La selezione invia il value scelto. Tema Wonder:
     * dropdown remota `data-wi-search-text`; tema Bootstrap: input testuale
     * (la ricerca remota è una feature del frontend).
     */
    public function searchText(string $url): InputSearchText
    {
        return $this->morphInto(InputSearchText::class)->url($url);
    }

    /**
     * Variante a selezione singola di `searchText()`: la scelta di una voce
     * invia direttamente il relativo value (`data-wi-search-radio`).
     */
    public function searchRadio(string $url): InputSearchRadio
    {
        return $this->morphInto(InputSearchRadio::class)->url($url);
    }

    public function checkbox(): InputCheckbox
    {
        return $this->morphInto(InputCheckbox::class);
    }

    /**
     * Upload "classic" (form-control + lista file ammessi/max/peso).
     * Il parametro `$accept` identifica la *categoria* di file accettata
     * (es. `image`, `pdf`, `video`, `font`, `media`): il renderer la
     * traduce in attributo `accept="..."` e in label informativa.
     */
    public function file(string $accept = 'image'): InputFile
    {
        return $this->morphInto(InputFile::class)->accept($accept);
    }

    /**
     * Upload drag&drop (Filepond). `$accept` come in {@see inputFile()};
     * `$uploader` è la strategia di upload lato client (`classic` o un
     * uploader registrato).
     */
    public function fileDragDrop(string $accept = 'image', string $uploader = 'classic'): InputFileDragDrop
    {
        return $this->morphInto(InputFileDragDrop::class)
            ->accept($accept)
            ->uploader($uploader);
    }

    public function country(?string $stateField = null): InputCountry
    {
        $input = $this->morphInto(InputCountry::class);

        return $stateField !== null ? $input->stateField($stateField) : $input;
    }

    public function states(?string $country = null): InputStates
    {
        $input = $this->morphInto(InputStates::class);

        return $country !== null ? $input->country($country) : $input;
    }

    public function phonePrefix(): InputPhonePrefix
    {
        return $this->morphInto(InputPhonePrefix::class);
    }

    public function repeater(array $columns = []): InputRepeater
    {
        return $this->morphInto(InputRepeater::class)->columns($columns);
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
    public function acceptDocument(string $type): InputAcceptDocument
    {
        return $this->morphInto(InputAcceptDocument::class)->documentType($type);
    }

    /**
     * Text input + bottone "GENERA" (Wonder\Elements\Form\Components\TextGenerator).
     *
     * @param string|null $callback   Funzione JS chiamata al click (default `generateCode`).
     * @param string|null $buttonLabel Label del bottone (default `GENERA`).
     */
    public function textGenerator(?string $callback = null, ?string $buttonLabel = null): InputTextGenerator
    {
        $input = $this->morphInto(InputTextGenerator::class);

        if ($callback !== null) {
            $input->callback($callback);
        }

        if ($buttonLabel !== null) {
            $input->buttonLabel($buttonLabel);
        }

        return $input;
    }

    /**
     * Lista checkbox/radio ad albero jsTree
     * (Wonder\Elements\Form\Components\CheckTree).
     *
     * @param array  $options    Opzioni (può contenere `child` per sotto-livelli).
     * @param bool   $searchBar  Aggiunge la barra di ricerca testuale.
     * @param string $inputType  'checkbox' (default) o 'radio'.
     */
    public function checkTree(array $options = [], bool $searchBar = false, string $inputType = 'checkbox'): InputCheckTree
    {
        return $this->morphInto(InputCheckTree::class)
            ->options($options)
            ->searchBar($searchBar)
            ->inputType($inputType);
    }

    /**
     * Check (checkbox/radio) con risultati caricati via AJAX
     * (Wonder\Elements\Form\Components\DynamicCheck).
     */
    public function dynamicCheck(string $url, string $inputType = 'checkbox'): InputDynamicCheck
    {
        return $this->morphInto(InputDynamicCheck::class)
            ->url($url)
            ->inputType($inputType);
    }

    /**
     * Toggle Si/No a 3 stati (null/true/false)
     * (Wonder\Elements\Form\Components\CheckBoolean).
     *
     * @param array $values Tripla [valueNull, valueTrue, valueFalse] dei valori
     *                      effettivamente postati dal form. Default `['', 'true', 'false']`.
     */
    public function checkBoolean(array $values = ['', 'true', 'false'], ?string $trueLabel = null, ?string $falseLabel = null): InputCheckBoolean
    {
        $input = $this->morphInto(InputCheckBoolean::class)->values($values);

        if ($trueLabel !== null) {
            $input->trueLabel($trueLabel);
        }

        if ($falseLabel !== null) {
            $input->falseLabel($falseLabel);
        }

        return $input;
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
    public function recaptcha(?string $action = null, ?string $theme = null, ?string $size = null): InputReCaptcha
    {
        $input = $this->morphInto(InputReCaptcha::class);

        if ($action !== null) {
            $input->action($action);
        }

        if ($theme !== null) {
            $input->theme($theme);
        }

        if ($size !== null) {
            $input->size($size);
        }

        return $input;
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
    public function googleAddress(array $restriction = [], ?string $alias = null): InputGoogleAddress
    {
        $input = $this->morphInto(InputGoogleAddress::class)->restriction($restriction);

        return $alias !== null ? $input->alias($alias) : $input;
    }

    # ------------------------------------------------------------------
    # Shim retro-compatibili
    #
    # Ognuno di questi modificatori vive ora sulla classe del tipo che lo
    # supporta; restano qui perché una catena può ancora chiamarli *prima*
    # del type-helper (`FormField::key('x')->options([...])->select()`), e in
    # quel punto l'oggetto è ancora la facade. Scrivono nello schema, che il
    # morph poi trasferisce alla classe tipizzata: il risultato è identico.
    #
    # Attenzione (semantica invariata dal refactor): se il type-helper riceve
    # lo stesso valore come argomento, il suo default vince — `->options([...])
    # ->select()` perde le opzioni, perché `select()` applica il proprio
    # `$options = []` dopo il morph. Vale per options/version/multiple/
    # searchBar/dateMin/dateMax/accept/uploader. Gli shim sono affidabili per i
    # modificatori che il type-helper NON prende come argomento (`decimals()`,
    # `minLength()`, `maxFile()`, `extensions()`, ...).
    #
    # Nel codice nuovo chiama prima il type-helper: `->select()->options([...])`.
    # ------------------------------------------------------------------

    /**
     * @deprecated Usa `->select()->options(...)` (o il type-helper del caso):
     *             il modificatore vive su `Inputs\InputSelect`, `InputRadio`,
     *             `InputCheckbox`, `InputCheckTree`, `InputTextList`,
     *             `InputCountry`.
     */
    public function options(array $options): self
    {
        $this->schema['options'] = $options;

        return $this;
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputRadio`,
     *             `InputCheckbox`, `InputCheckTree`.
     */
    public function searchBar(bool $searchBar = true): self
    {
        $this->schema['search_bar'] = $searchBar;

        return $this;
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputSelect`,
     *             `InputSelectSearch`, `InputTextarea`, `InputTextList`.
     */
    public function version(?string $version): self
    {
        $this->schema['version'] = $version;

        return $this;
    }

    /**
     * @deprecated Il modificatore vive sulle stesse classi di `version()`.
     */
    public function old(): self
    {
        return $this->version('old');
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputSelect`,
     *             `InputSelectSearch`, `InputFile`, `InputFileDragDrop`.
     */
    public function multiple(bool $multiple = true): self
    {
        $this->schema['multiple'] = $multiple;

        return $multiple ? $this->attribute('multiple') : $this;
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputFileDragDrop`.
     */
    public function uploader(string $uploader = 'classic'): self
    {
        $this->schema['uploader'] = trim($uploader);

        return $this;
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputFile` (e sottoclassi).
     */
    public function maxFile(int $count): self
    {
        return $this->prepare('max_file', $count);
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputFile` (e sottoclassi).
     */
    public function maxSize(int $size): self
    {
        return $this->prepare('max_size', $size);
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputFile` (e sottoclassi).
     */
    public function extensions(string|array $extensions): self
    {
        return $this->extensionsSet($extensions);
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputTextDate`,
     *             `InputDate`, `InputDateRange`.
     */
    public function dateMin(?string $dateMin): self
    {
        $this->schema['date_min'] = $dateMin;

        return $this;
    }

    /**
     * @deprecated Il modificatore vive sulle stesse classi di `dateMin()`.
     */
    public function dateMax(?string $dateMax): self
    {
        $this->schema['date_max'] = $dateMax;

        return $this;
    }

    /**
     * @deprecated Il modificatore vive su `Inputs\InputTime`.
     */
    public function timeStep(?int $timeStep): self
    {
        $this->schema['time_step'] = $timeStep;

        return $this;
    }

    /**
     * Configurazione del formatting numerico.
     *
     * @deprecated I sei setters vivono su `Inputs\InputNumber` (e quindi su
     *             `InputPrice` / `InputPercentige`), che ne documenta la
     *             differenza fra `decimal()` e `decimals()`.
     */
    public function decimal(int $decimal): self
    {
        return $this->numberConfig('decimal', max(0, $decimal));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputNumber`. */
    public function decimalSeparator(string $separator): self
    {
        return $this->numberConfig('decimal_separator', $separator);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputNumber`. */
    public function groupSeparator(string $separator): self
    {
        return $this->numberConfig('group_separator', $separator);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputNumber`. */
    public function symbol(string $symbol): self
    {
        return $this->numberConfig('symbol', $symbol);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputNumber`. */
    public function symbolPlacement(string $placement): self
    {
        return $this->numberSymbolPlacement($placement);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputNumber`. */
    public function decimals(int $decimals): self
    {
        return $this->numberConfig('decimals', max(0, $decimals));
    }

    /**
     * Password policy.
     *
     * @deprecated I cinque setters vivono su `Inputs\InputPassword`, che
     *             documenta dove finiscono le regole e chi le rilegge
     *             (render + validazione server-side).
     */
    public function minLength(int $length): self
    {
        return $this->passwordRuleSet('min_length', max(0, $length));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputPassword`. */
    public function requireUppercase(bool $required = true): self
    {
        return $this->passwordRuleSet('uppercase', $required);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputPassword`. */
    public function requireLowercase(bool $required = true): self
    {
        return $this->passwordRuleSet('lowercase', $required);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputPassword`. */
    public function requireNumber(bool $required = true): self
    {
        return $this->passwordRuleSet('number', $required);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputPassword`. */
    public function requireSpecial(bool $required = true): self
    {
        return $this->passwordRuleSet('special', $required);
    }

    /**
     * Configurazione del repeater.
     *
     * @deprecated I setter `nested()`, `relation()` e `repeater*()` vivono su
     *             `Inputs\InputRepeater`: chiama prima `->repeater([...])`.
     */
    public function nested(bool $nested = true): self
    {
        return $this->context('nested', $nested);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function relation(object $relation): self
    {
        return $this->context('relation', $relation);
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function repeaterAddLabel(string $label): self
    {
        return $this->context('add_label', trim($label));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function repeaterButtonClass(string $class): self
    {
        return $this->context('add_button_class', trim($class));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function repeaterDeleteTitle(string $title): self
    {
        return $this->context('delete_modal_title', trim($title));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function repeaterDeleteText(string $text): self
    {
        return $this->context('delete_modal_text', trim($text));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function repeaterDeleteCancelLabel(string $label): self
    {
        return $this->context('delete_modal_cancel_label', trim($label));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function repeaterDeleteConfirmLabel(string $label): self
    {
        return $this->context('delete_modal_confirm_label', trim($label));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function repeaterDeleteConfirmClass(string $class): self
    {
        return $this->context('delete_modal_confirm_class', trim($class));
    }

    /** @deprecated Il modificatore vive su `Inputs\InputRepeater`. */
    public function repeaterSortable(bool $sortable = true): self
    {
        return $this->context('sortable', $sortable);
    }
}
