<?php

namespace Wonder\Backend\Table\Badge;

/**
 * Badge booleano per le tabelle backend: unica fonte di verità per testi,
 * icone, colori e varianti di render degli stati active/visible/evidence
 * (e di qualunque badge on/off custom).
 *
 * Classe pura: nessun global, nessuna query. L'HTML replica byte-per-byte
 * l'output delle funzioni legacy returnBadge()/returnButton() di
 * app/function/backend/plugin.php per non toccare il CSS esistente.
 *
 * Il badge NON è cliccabile di default: il toggle diretto sul badge va
 * forzato con clickable() e richiede una action.
 */
final class BooleanBadge
{
    private bool $value;

    private string $onText = '';
    private string $onIcon = '';
    private string $onColor = '';
    private string $onButtonText = '';

    private string $offText = '';
    private string $offIcon = '';
    private string $offColor = '';
    private string $offButtonText = '';

    private string $action = '';
    private bool $clickable = false;

    private function __construct(mixed $value)
    {
        $this->value = ($value === true || $value === 'true');
    }

    public static function make(mixed $value): self
    {
        return new self($value);
    }

    public static function preset(string $name, mixed $value): ?self
    {
        return match (trim($name)) {
            'active' => self::active($value),
            'visible' => self::visible($value),
            'evidence' => self::evidence($value),
            default => null,
        };
    }

    public static function active(mixed $value): self
    {
        return self::make($value)
            ->on('Abilitato', 'bi bi-check-circle', 'success', 'Disabilita')
            ->off('Disabilitato', 'bi bi-x-circle', 'danger', 'Abilita');
    }

    public static function visible(mixed $value): self
    {
        return self::make($value)
            ->on('Visibile', 'bi bi-eye', 'success', 'Nascondi')
            ->off('Nascosto', 'bi bi-eye-slash', 'danger', 'Mostra');
    }

    public static function evidence(mixed $value): self
    {
        return self::make($value)
            ->on('In evidenza', 'bi bi-star-fill', 'warning', 'Rimuovi evidenza')
            ->off('', '', '', 'In evidenza');
    }

    public function on(string $text, string $icon = '', string $color = '', string $buttonText = ''): self
    {
        $this->onText = $text;
        $this->onIcon = $icon;
        $this->onColor = $color;
        $this->onButtonText = $buttonText;

        return $this;
    }

    public function off(string $text, string $icon = '', string $color = '', string $buttonText = ''): self
    {
        $this->offText = $text;
        $this->offIcon = $icon;
        $this->offColor = $color;
        $this->offButtonText = $buttonText;

        return $this;
    }

    public function action(string $action): self
    {
        $this->action = trim($action);

        return $this;
    }

    public function clickable(bool $clickable = true): self
    {
        $this->clickable = $clickable;

        return $this;
    }

    public function text(): string
    {
        return $this->value ? $this->onText : $this->offText;
    }

    public function buttonText(): string
    {
        return $this->value ? $this->onButtonText : $this->offButtonText;
    }

    private function iconClass(): string
    {
        return $this->value ? $this->onIcon : $this->offIcon;
    }

    private function color(): string
    {
        return $this->value ? $this->onColor : $this->offColor;
    }

    public function icon(): string
    {
        return $this->iconClass() === '' ? '' : "<i class='{$this->iconClass()}'></i>";
    }

    public function tooltip(): string
    {
        if ($this->iconClass() === '' || $this->text() === '') {
            return '';
        }

        return "<i class='{$this->iconClass()}' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='{$this->text()}'></i>";
    }

    public function badge(): string
    {
        if ($this->color() === '' || $this->text() === '') {
            return '';
        }

        return $this->wrapClickable("<span class='badge text-bg-{$this->color()}'>".strtoupper($this->text())."</span>");
    }

    public function badgeTooltip(): string
    {
        if ($this->color() === '' || $this->text() === '' || $this->icon() === '') {
            return '';
        }

        return $this->wrapClickable("<span class='badge text-bg-{$this->color()}' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='{$this->text()}'>{$this->icon()}</span>");
    }

    public function automaticResize(): string
    {
        if ($this->color() === '' || $this->icon() === '' || $this->text() === '') {
            return '';
        }

        return $this->wrapClickable("<span class='badge text-bg-{$this->color()}'><span class='pc-none'>{$this->icon()}</span><span class='phone-none'>".strtoupper($this->text())."</span></span>");
    }

    public function button(): string
    {
        if ($this->buttonText() === '' || $this->action === '') {
            return '';
        }

        return "<a class='dropdown-item ' {$this->action} role='button'>{$this->buttonText()}</a>";
    }

    public function render(string $variant): string
    {
        return match (trim($variant)) {
            'badge' => $this->badge(),
            'icon' => $this->icon(),
            'tooltip' => $this->tooltip(),
            'badgeTooltip', 'badgeIcon' => $this->badgeTooltip(),
            'automaticResize' => $this->automaticResize(),
            'button' => $this->button(),
            'text' => $this->text(),
            default => '',
        };
    }

    /**
     * Oggetto con la stessa shape del merge returnBadge()+returnButton()
     * legacy, per i wrapper deprecati di plugin.php e per il menu azioni.
     */
    public function legacyObject(): object
    {
        return (object) [
            'color' => function_exists('bootstrapColor') ? bootstrapColor($this->color()) : '',
            'bootstrapColor' => $this->color(),
            'text' => $this->text(),
            'classIcon' => $this->iconClass(),
            'icon' => $this->icon(),
            'tooltip' => $this->tooltip(),
            'badge' => $this->badge(),
            'badgeTooltip' => $this->badgeTooltip(),
            'badgeIcon' => $this->badgeTooltip(),
            'automaticResize' => $this->automaticResize(),
            'action' => $this->action,
            'button' => $this->button(),
        ];
    }

    private function wrapClickable(string $html): string
    {
        if (!$this->clickable || $this->action === '' || $html === '') {
            return $html;
        }

        return "<span role='button' {$this->action}>$html</span>";
    }
}
