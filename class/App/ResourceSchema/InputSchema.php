<?php

namespace Wonder\App\ResourceSchema;

use Exception;
use Wonder\App\ResourceSchema\Inputs;

/**
 * Factory-dispatcher per le classi `Input*`, mirror esatto di
 * `Wonder\Data\UploadSchema` (class/Data/UploadSchema.php).
 *
 *   InputSchema::key('email')->text()->required()
 *   InputSchema::key('password')->password()->minLength(8)->requireSpecial()
 *   InputSchema::key('stato')->select()->options(['1' => 'Attivo'])
 *
 * È l'alternativa "senza facade" a `FormField::key(...)->tipo()`: stesso
 * risultato, un morph in meno. Il proxy **non inoltra argomenti** — istanzia il
 * tipo e basta — quindi le opzioni e gli altri modificatori vanno concatenati
 * dopo, sulla classe tipizzata che li espone.
 *
 * Il `FormFieldElementFactory` accetta indifferentemente queste istanze e
 * quelle prodotte da `FormField` (type hint comune `Input`).
 */
class InputSchema
{
    /** @var array<string, class-string<Input>> */
    private const INPUTS = [
        'text' => Inputs\InputText::class,
        'hidden' => Inputs\InputHidden::class,
        'email' => Inputs\InputEmail::class,
        'tel' => Inputs\InputPhone::class,
        'phone' => Inputs\InputPhone::class,
        'url' => Inputs\InputUrl::class,
        'color' => Inputs\InputColor::class,
        'password' => Inputs\InputPassword::class,
        'number' => Inputs\InputNumber::class,
        'price' => Inputs\InputPrice::class,
        'percentige' => Inputs\InputPercentige::class,
        'textarea' => Inputs\InputTextarea::class,
        'textgenerator' => Inputs\InputTextGenerator::class,
        'textdate' => Inputs\InputTextDate::class,
        'textdatetime' => Inputs\InputTextDatetime::class,
        'dateinput' => Inputs\InputDate::class,
        'daterange' => Inputs\InputDateRange::class,
        'timeinput' => Inputs\InputTime::class,
        'select' => Inputs\InputSelect::class,
        'selectsearch' => Inputs\InputSelectSearch::class,
        'textlist' => Inputs\InputTextList::class,
        'searchtext' => Inputs\InputSearchText::class,
        'searchradio' => Inputs\InputSearchRadio::class,
        'radio' => Inputs\InputRadio::class,
        'checkbox' => Inputs\InputCheckbox::class,
        'checktree' => Inputs\InputCheckTree::class,
        'dynamiccheck' => Inputs\InputDynamicCheck::class,
        'checkboolean' => Inputs\InputCheckBoolean::class,
        'file' => Inputs\InputFile::class,
        'filedragdrop' => Inputs\InputFileDragDrop::class,
        'country' => Inputs\InputCountry::class,
        'states' => Inputs\InputStates::class,
        'phoneprefix' => Inputs\InputPhonePrefix::class,
        'repeater' => Inputs\InputRepeater::class,
        'acceptdocument' => Inputs\InputAcceptDocument::class,
        'recaptcha' => Inputs\InputReCaptcha::class,
        'googleaddress' => Inputs\InputGoogleAddress::class,
    ];

    public function __construct(public string $key)
    {
    }

    public static function key(string $key): static
    {
        return new self($key);
    }

    /**
     * Proxy magico: `InputSchema::key('p')->password()` istanzia
     * `InputPassword`, `->number()` istanzia `InputNumber`, ecc.
     */
    public function __call(string $method, array $args): Input
    {
        $class = self::INPUTS[strtolower($method)] ?? null;

        if ($class === null) {
            throw new Exception("Input {$method} non supportato in InputSchema. Tipi disponibili: ".implode(', ', array_keys(self::INPUTS)));
        }

        return new $class($this->key);
    }
}
