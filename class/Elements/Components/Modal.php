<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\HasPartAttributes;
use Wonder\Elements\Concerns\IsContainer;

/**
 * Una finestra Bootstrap scritta nel layout come un Accordion: titolo, corpo
 * a griglia con i suoi campi, bottoni in fondo.
 *
 * ```php
 * Modal::make('Costo del fornitore')
 *     ->id('wi-cost-modal')
 *     ->columns(12)
 *     ->components([
 *         FormField::key('wi_cost_code')->text()->label('Codice fornitore')->columnSpan(6),
 *         FormField::key('wi_cost_price')->price()->label('Costo')->columnSpan(6),
 *     ])
 *     ->footer([
 *         Button::make('Annulla')->variant('secondary')->attr('data-bs-dismiss', 'modal'),
 *         Button::make('Salva')->attr('data-wi-cost-save', 'true'),
 *     ])
 * ```
 *
 * Senza `form()` dentro non c'è un `<form>` e la finestra esce dal form
 * della Resource appena la pagina è pronta: i suoi campi li legge e li
 * scrive uno script della pagina, non partono con il record. La apre un
 * bottone con `Button::opensModal($id)`. Il frontend è esplicito:
 * `frontend()->id(...)` usa il componente `wi-modal` della lib. Senza opt-in
 * mantiene il comportamento backend-only precedente.
 *
 * Con `form()` la finestra è un form: corpo e bottoni stanno in un
 * `<form>` con il token CSRF e i campi nascosti, e in fondo ci sono
 * Annulla e poi Salva (`cancel()`, `submit()`). Va resa fuori da altri form
 * (un form annidato il browser lo butta via): nelle pagine account sta in
 * `page_modals`.
 *
 * ```php
 * Modal::make('Registra pagamento')
 *     ->id('pay-12')
 *     ->help('Il pagamento resta modificabile fino alla chiusura.')
 *     ->form(action: $url, hidden: ['order_id' => 12])
 *     ->components([FormField::key('amount')->price()->required()->columnSpan(6)])
 *     ->cancel('Indietro')
 *     ->submit('Registra', variant: 'success');
 * ```
 */
class Modal extends Component
{
    use HasPartAttributes, IsContainer;

    private const ALLOWED_SIZES = ['', 'sm', 'lg', 'xl'];
    private const ALLOWED_METHODS = ['get', 'post'];

    /** I bottoni in fondo, di solito `Components\Button`. */
    public array $footer = [];

    public function __construct(string $title = '')
    {
        $this->schema('title', $title);
        $this->columnSpan(12);
    }

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function title(string $title): self
    {
        return $this->schema('title', $title);
    }

    /** Explicit opt-in: existing backend-only modals stay invisible in Wonder. */
    public function frontend(bool $enabled = true): self
    {
        return $this->schema('frontend', $enabled);
    }

    public function getTitle(): string
    {
        return (string) ($this->getSchema('title') ?? '');
    }

    /** `sm`, `lg`, `xl` o stringa vuota per la misura normale. */
    public function size(string $size): self
    {
        $normalized = strtolower(trim($size));

        if (!in_array($normalized, self::ALLOWED_SIZES, true)) {
            throw new InvalidArgumentException(
                'Dimensione della finestra non valida. Valori ammessi: sm, lg, xl'
            );
        }

        return $this->schema('size', $normalized);
    }

    /** Il corpo scorre e l'intestazione con i bottoni resta ferma. */
    public function scrollable(bool $scrollable = true): self
    {
        return $this->schema('scrollable', $scrollable);
    }

    /**
     * I bottoni in fondo, scritti a mano: sostituiscono del tutto Annulla e
     * Salva di `cancel()` e `submit()`.
     *
     * @param array<int, Component> $components
     */
    public function footer(array $components): self
    {
        $this->footer = array_values($components);

        return $this;
    }

    /**
     * Corpo e bottoni in un `<form>`, con il token CSRF per i POST e un
     * `<input type="hidden">` per ogni campo di `$hidden`. In fondo Annulla e
     * Salva, se `footer()` non li sostituisce.
     *
     * @param array<string, scalar|null> $hidden
     */
    public function form(string $action, string $method = 'post', array $hidden = []): self
    {
        $method = strtolower(trim($method));

        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new InvalidArgumentException('Metodo del form non valido. Valori ammessi: get, post');
        }

        foreach ($hidden as $name => $value) {
            if (!is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException('I campi nascosti della finestra vogliono un nome.');
            }

            if ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException("Il campo nascosto «{$name}» deve avere un valore scalare.");
            }
        }

        return $this->schema('form', [
            'action' => trim($action),
            'method' => $method,
            'hidden' => $hidden,
        ]);
    }

    /** Il bottone che invia il form; senza etichetta «Salva» tradotto. */
    public function submit(string $label = '', string $variant = 'primary'): self
    {
        $variant = strtolower(trim($variant));

        return $this->schema('submit', [
            'label' => trim($label),
            'variant' => $variant !== '' ? $variant : 'primary',
        ]);
    }

    /** Il bottone che chiude la finestra; senza etichetta «Indietro» tradotto. */
    public function cancel(string $label = ''): self
    {
        return $this->schema('cancel', ['label' => trim($label)]);
    }

    /** Un testo di aiuto, mostrato come icona con tooltip accanto al titolo. */
    public function help(string $text): self
    {
        return $this->schema('help', trim($text));
    }

    public function dialogClass(string $class): self
    {
        return $this->setPartClass('dialog', $class);
    }

    public function headerClass(string $class): self
    {
        return $this->setPartClass('header', $class);
    }

    public function titleClass(string $class): self
    {
        return $this->setPartClass('title', $class);
    }

    public function bodyClass(string $class): self
    {
        return $this->setPartClass('body', $class);
    }

    public function footerClass(string $class): self
    {
        return $this->setPartClass('footer', $class);
    }

    /**
     * I bottoni in fondo: quelli di `footer()` se ci sono, altrimenti Annulla
     * e poi Salva. Con `form()` ci sono sempre entrambi.
     *
     * @return array<int, mixed>
     */
    public function footerComponents(): array
    {
        if ($this->footer !== []) {
            return $this->footer;
        }

        $hasForm = is_array($this->getSchema('form'));
        $cancel = $this->getSchema('cancel');
        $submit = $this->getSchema('submit');
        $buttons = [];

        if (is_array($cancel) || $hasForm) {
            $label = (string) ($cancel['label'] ?? '');
            $buttons[] = Button::make($label !== '' ? $label : (string) \__t('components.buttons.cancel'))
                ->type('button')
                ->variant('secondary')
                ->outline()
                ->schema('modal_cancel', true);
        }

        if (is_array($submit) || $hasForm) {
            $label = (string) ($submit['label'] ?? '');
            $buttons[] = Button::make($label !== '' ? $label : (string) \__t('components.buttons.save'))
                ->type('submit')
                ->variant((string) ($submit['variant'] ?? 'primary'));
        }

        return $buttons;
    }
}
