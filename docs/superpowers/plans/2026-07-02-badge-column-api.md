# API badge booleani per tabelle backend — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminare la dipendenza dai global `$NAME`/`$PATH` nel render delle colonne booleane (`active`/`visible`/`evidence`) introducendo `BooleanBadge` come unica fonte di verità, helper dichiarativi su `TableColumn`, rimappatura del percorso legacy e whitelist per `call_user_func`.

**Architecture:** Un value object `Wonder\Backend\Table\Badge\BooleanBadge` (puro, zero global) renderizza tutte le varianti badge/button. `TableColumn` scrive nello schema un descrittore dichiarativo `badge` che viaggia nel config della tabella come oggi `function`. `Field` risolve il descrittore col contesto già iniettato e rimappa internamente `function('active'|'visible'|'evidence')` sul nuovo percorso. Le funzioni di `plugin.php` diventano wrapper deprecati. Spec: `docs/superpowers/specs/2026-07-02-badge-column-api-design.md`.

**Tech Stack:** PHP 8.2, Composer PSR-4 (`Wonder\` → `class/`), test = script PHP standalone con helper `eq()` ed exit code (stile `tests/Backend/Table/SearchFieldResolverTest.php`). Nessun PHPUnit, nessun DB nei test.

## Global Constraints

- Lavorare **direttamente sul branch `main`** (richiesta esplicita dell'utente).
- HTML di output **byte-identico** a `returnBadge()`/`returnButton()` attuali (classi `pc-none`/`phone-none`, `badge text-bg-{color}`, `dropdown-item ` con spazio finale) — il CSS non va toccato.
- Il badge NON è cliccabile di default; il toggle sul badge è opt-in esplicito (`clickable`).
- Nomi API: `activeBadge()` / `visibleBadge()` / `evidenceBadge()` / `booleanBadge()` — mai "Toggle".
- Retrocompatibilità totale: `->function('active'|'visible'|'evidence', ...)` e le funzioni di `plugin.php` continuano a funzionare.
- Valore "on" solo se `=== 'true'` (stringa) o `=== true`; tutto il resto è off.
- Ogni file PHP toccato: `php -l` prima del commit.
- Commit frequenti, un commit per task, messaggi in stile repo (`feat(table): ...`, `fix: ...`, `docs: ...`) con trailer `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.
- Divergenza accettata (approvata in spec): `->function('evidence', 'id', 'badge')` in stato off oggi renderizza `NON IN EVIDENZA`; col preset renderizza `''` (parità col menu azioni). Nessun uso nel framework.

---

### Task 1: `BooleanBadge` — value object + renderer

**Files:**
- Create: `class/Backend/Table/Badge/BooleanBadge.php`
- Test: `tests/Backend/Table/Badge/BooleanBadgeTest.php`

**Interfaces:**
- Consumes: niente (classe pura, zero dipendenze runtime; `bootstrapColor()` solo se definita).
- Produces (usato dai Task 4, 5, 7):
  - `BooleanBadge::make(mixed $value): self`
  - `BooleanBadge::preset(string $name, mixed $value): ?self` (nomi: `active`, `visible`, `evidence`; altrimenti `null`)
  - `BooleanBadge::active(mixed $value): self`, `::visible(...)`, `::evidence(...)`
  - `->on(string $text, string $icon = '', string $color = '', string $buttonText = ''): self`, `->off(...): self`
  - `->action(string $action): self`, `->clickable(bool $clickable = true): self`
  - `->badge(): string`, `->icon(): string`, `->tooltip(): string`, `->badgeTooltip(): string`, `->automaticResize(): string`, `->button(): string`, `->text(): string`
  - `->render(string $variant): string` (variant: `badge|icon|tooltip|badgeTooltip|badgeIcon|automaticResize|button|text`; sconosciuta → `''`)
  - `->legacyObject(): object` con chiavi `color, bootstrapColor, text, classIcon, icon, tooltip, badge, badgeTooltip, badgeIcon, automaticResize, action, button`

- [ ] **Step 1: Scrivi il test fallente**

Crea `tests/Backend/Table/Badge/BooleanBadgeTest.php`:

```php
<?php
/** php tests/Backend/Table/Badge/BooleanBadgeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

use Wonder\Backend\Table\Badge\BooleanBadge;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

// --- preset active, stato on: HTML identico a returnBadge() legacy
eq('active on automaticResize',
    BooleanBadge::active('true')->automaticResize(),
    "<span class='badge text-bg-success'><span class='pc-none'><i class='bi bi-check-circle'></i></span><span class='phone-none'>ABILITATO</span></span>"
);
eq('active off automaticResize',
    BooleanBadge::active('false')->automaticResize(),
    "<span class='badge text-bg-danger'><span class='pc-none'><i class='bi bi-x-circle'></i></span><span class='phone-none'>DISABILITATO</span></span>"
);
eq('active on badge',
    BooleanBadge::active('true')->badge(),
    "<span class='badge text-bg-success'>ABILITATO</span>"
);
eq('active on tooltip',
    BooleanBadge::active('true')->tooltip(),
    "<i class='bi bi-check-circle' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Abilitato'></i>"
);

// --- valore non-'true' è off (comportamento legacy: confronto con 'true')
eq('value 1 is off', BooleanBadge::active('1')->text(), 'Disabilitato');
eq('value bool true is on', BooleanBadge::active(true)->text(), 'Abilitato');

// --- preset visible / evidence
eq('visible off automaticResize',
    BooleanBadge::visible('false')->automaticResize(),
    "<span class='badge text-bg-danger'><span class='pc-none'><i class='bi bi-eye-slash'></i></span><span class='phone-none'>NASCOSTO</span></span>"
);
eq('evidence on automaticResize',
    BooleanBadge::evidence('true')->automaticResize(),
    "<span class='badge text-bg-warning'><span class='pc-none'><i class='bi bi-star-fill'></i></span><span class='phone-none'>IN EVIDENZA</span></span>"
);
eq('evidence off renders empty', BooleanBadge::evidence('false')->automaticResize(), '');
eq('evidence off badge empty', BooleanBadge::evidence('false')->badge(), '');

// --- preset() factory
eq('preset active', BooleanBadge::preset('active', 'true')->text(), 'Abilitato');
eq('preset unknown is null', BooleanBadge::preset('ghost', 'true'), null);

// --- button: identico a returnButton($text, $action) legacy (senza colore)
eq('button on',
    BooleanBadge::active('true')->action("onclick=\"x()\"")->button(),
    "<a class='dropdown-item ' onclick=\"x()\" role='button'>Disabilita</a>"
);
eq('button without action is empty', BooleanBadge::active('true')->button(), '');

// --- clickable: OFF di default, opt-in esplicito
eq('badge not clickable by default',
    BooleanBadge::active('true')->action("onclick=\"x()\"")->badge(),
    "<span class='badge text-bg-success'>ABILITATO</span>"
);
eq('badge clickable when forced',
    BooleanBadge::active('true')->action("onclick=\"x()\"")->clickable()->badge(),
    "<span role='button' onclick=\"x()\"><span class='badge text-bg-success'>ABILITATO</span></span>"
);
eq('clickable without action is no-op',
    BooleanBadge::active('true')->clickable()->badge(),
    "<span class='badge text-bg-success'>ABILITATO</span>"
);

// --- render() dispatch
eq('render automaticResize', BooleanBadge::active('true')->render('automaticResize'), BooleanBadge::active('true')->automaticResize());
eq('render badgeIcon alias', BooleanBadge::active('true')->render('badgeIcon'), BooleanBadge::active('true')->badgeTooltip());
eq('render unknown variant empty', BooleanBadge::active('true')->render('ghost'), '');

// --- generico con on/off custom
$custom = BooleanBadge::make('true')
    ->on('Aperto', 'bi bi-unlock', 'success', 'Chiudi')
    ->off('Chiuso', 'bi bi-lock', 'danger', 'Apri');
eq('custom on badge', $custom->badge(), "<span class='badge text-bg-success'>APERTO</span>");

// --- legacyObject: shape identica al merge returnBadge+returnButton
$obj = BooleanBadge::active('true')->action("onclick=\"x()\"")->legacyObject();
eq('legacyObject keys',
    array_keys((array) $obj),
    ['color', 'bootstrapColor', 'text', 'classIcon', 'icon', 'tooltip', 'badge', 'badgeTooltip', 'badgeIcon', 'automaticResize', 'action', 'button']
);
eq('legacyObject text', $obj->text, 'Abilitato');
eq('legacyObject bootstrapColor', $obj->bootstrapColor, 'success');
eq('legacyObject classIcon', $obj->classIcon, 'bi bi-check-circle');
eq('legacyObject action', $obj->action, "onclick=\"x()\"");
eq('legacyObject badgeIcon equals badgeTooltip', $obj->badgeIcon, $obj->badgeTooltip);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Backend/Table/Badge/BooleanBadgeTest.php`
Expected: errore fatale `Class "Wonder\Backend\Table\Badge\BooleanBadge" not found`.

- [ ] **Step 3: Implementa `BooleanBadge`**

Crea `class/Backend/Table/Badge/BooleanBadge.php`:

```php
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
```

- [ ] **Step 4: Lint + test verdi**

Run: `php -l class/Backend/Table/Badge/BooleanBadge.php && composer dumpautoload -q && php tests/Backend/Table/Badge/BooleanBadgeTest.php`
Expected: `ALL PASS`, exit 0.

- [ ] **Step 5: Commit**

```bash
git add class/Backend/Table/Badge/BooleanBadge.php tests/Backend/Table/Badge/BooleanBadgeTest.php
git commit -m "feat(table): add BooleanBadge value object for boolean column badges

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: `ColumnFunctionRegistry` — whitelist per le funzioni colonna

**Files:**
- Create: `class/Backend/Table/ColumnFunctionRegistry.php`
- Test: `tests/Backend/Table/ColumnFunctionRegistryTest.php`

**Interfaces:**
- Consumes: `Wonder\App\ResourceRegistry::classes(): array` (esistente); `Resource::tableSchema(): array` di oggetti con `toArray()`.
- Produces (usato dal Task 6):
  - `ColumnFunctionRegistry::isAllowed(string $name): bool`
  - `ColumnFunctionRegistry::allow(string ...$names): void` (estensione per siti/moduli con pagine legacy custom)
  - `ColumnFunctionRegistry::allowed(): array`
  - `ColumnFunctionRegistry::reset(): void` (per i test)

- [ ] **Step 1: Scrivi il test fallente**

Crea `tests/Backend/Table/ColumnFunctionRegistryTest.php`:

```php
<?php
/** php tests/Backend/Table/ColumnFunctionRegistryTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\ColumnFunctionRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

ColumnFunctionRegistry::reset();

// default del framework (usati dalle pagine legacy log email/consent)
eq('mailService allowed', ColumnFunctionRegistry::isAllowed('mailService'), true);
eq('mailLogStatus allowed', ColumnFunctionRegistry::isAllowed('mailLogStatus'), true);
eq('consentEventAction allowed', ColumnFunctionRegistry::isAllowed('consentEventAction'), true);
eq('consentEventSource allowed', ColumnFunctionRegistry::isAllowed('consentEventSource'), true);

// nomi arbitrari dal POST: negati
eq('system denied', ColumnFunctionRegistry::isAllowed('system'), false);
eq('strtoupper denied', ColumnFunctionRegistry::isAllowed('strtoupper'), false);
eq('empty string denied', ColumnFunctionRegistry::isAllowed(''), false);

// estensione esplicita per siti/moduli
ColumnFunctionRegistry::allow('mySiteFn');
eq('allowed after allow()', ColumnFunctionRegistry::isAllowed('mySiteFn'), true);
eq('trimmed lookup', ColumnFunctionRegistry::isAllowed(' mySiteFn '), true);

// reset azzera le estensioni
ColumnFunctionRegistry::reset();
eq('reset clears extra', ColumnFunctionRegistry::isAllowed('mySiteFn'), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Backend/Table/ColumnFunctionRegistryTest.php`
Expected: errore fatale `Class "Wonder\Backend\Table\ColumnFunctionRegistry" not found`.

- [ ] **Step 3: Implementa la classe**

Crea `class/Backend/Table/ColumnFunctionRegistry.php`:

```php
<?php

namespace Wonder\Backend\Table;

use Wonder\App\ResourceRegistry;

/**
 * Whitelist delle funzioni invocabili dalle colonne tabella via
 * ->function(). I nomi arrivano dal POST dell'endpoint list-table: senza
 * whitelist un client autenticato può far eseguire funzioni PHP arbitrarie.
 *
 * La whitelist è derivata server-side: funzioni dichiarate nei tableSchema()
 * delle Resource registrate + default del framework (pagine legacy) +
 * estensioni esplicite via allow() per siti/moduli con pagine legacy custom.
 *
 * active/visible/evidence/empty/permissions* NON passano da qui: sono
 * gestite come special-case interne di Field.
 */
final class ColumnFunctionRegistry
{
    private const FRAMEWORK_DEFAULTS = [
        'mailService',
        'mailLogStatus',
        'consentEventAction',
        'consentEventSource',
    ];

    /** @var array<int,string> */
    private static array $extra = [];

    /** @var array<int,string>|null cache per-request */
    private static ?array $resolved = null;

    public static function allow(string ...$names): void
    {
        foreach ($names as $name) {
            $name = trim($name);

            if ($name !== '' && !in_array($name, self::$extra, true)) {
                self::$extra[] = $name;
            }
        }

        self::$resolved = null;
    }

    public static function isAllowed(string $name): bool
    {
        $name = trim($name);

        return $name !== '' && in_array($name, self::allowed(), true);
    }

    /** @return array<int,string> */
    public static function allowed(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        return self::$resolved = array_values(array_unique(array_merge(
            self::FRAMEWORK_DEFAULTS,
            self::$extra,
            self::fromResources()
        )));
    }

    public static function reset(): void
    {
        self::$extra = [];
        self::$resolved = null;
    }

    /** @return array<int,string> */
    private static function fromResources(): array
    {
        $names = [];

        try {
            $classes = ResourceRegistry::classes();
        } catch (\Throwable) {
            return $names;
        }

        foreach ($classes as $resourceClass) {
            try {
                foreach ($resourceClass::tableSchema() as $column) {
                    if (!is_object($column) || !method_exists($column, 'toArray')) {
                        continue;
                    }

                    $schema = (array) $column->toArray();
                    $functionName = $schema['function']['name'] ?? null;

                    if (is_string($functionName) && trim($functionName) !== '') {
                        $names[] = trim($functionName);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $names;
    }
}
```

- [ ] **Step 4: Lint + test verdi**

Run: `php -l class/Backend/Table/ColumnFunctionRegistry.php && php tests/Backend/Table/ColumnFunctionRegistryTest.php`
Expected: `ALL PASS`, exit 0.

- [ ] **Step 5: Commit**

```bash
git add class/Backend/Table/ColumnFunctionRegistry.php tests/Backend/Table/ColumnFunctionRegistryTest.php
git commit -m "feat(table): add server-side whitelist for column render functions

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: API badge su `TableColumn`

**Files:**
- Modify: `class/App/ResourceSchema/TableColumn.php` (aggiungere metodi dopo `status()`, riga ~47)
- Test: `tests/App/ResourceSchema/TableColumnBadgeTest.php`

**Interfaces:**
- Consumes: `Column::schema(key, value)` e `Column::setType()` (base esistente, `class/Elements/Table/Column.php`); `toArray()` da `Wonder\Elements\Component`.
- Produces (descrittore letto dai Task 4-5 e propagato dal Task 4 in `ResourceTableRenderer`): chiave schema `badge`:

```php
['badge' => [
    'preset'    => 'active'|'visible'|'evidence'|null,
    'column'    => '<nome colonna>',
    'variant'   => 'automaticResize',   // default
    'clickable' => false,               // default
    'on'  => ['text' => ..., 'icon' => ..., 'color' => ..., 'button' => ...],  // solo generico
    'off' => [...],
]]
```

  Metodi: `activeBadge(bool $clickable = false)`, `visibleBadge(bool $clickable = false)`, `evidenceBadge(bool $clickable = false)`, `booleanBadge(?string $column = null)`, `badgeOn(string $text, string $icon = '', string $color = '', string $buttonText = '')`, `badgeOff(...)`, `badgeVariant(string $variant)`, `badgeClickable(bool $clickable = true)`.

- [ ] **Step 1: Scrivi il test fallente**

Crea `tests/App/ResourceSchema/TableColumnBadgeTest.php`:

```php
<?php
/** php tests/App/ResourceSchema/TableColumnBadgeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

// helper semantico: descrittore preset, non cliccabile di default
$schema = TableColumn::key('active')->activeBadge()->size('little')->toArray();
eq('activeBadge type', $schema['type'], 'badge');
eq('activeBadge descriptor', $schema['badge'], [
    'preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => false,
]);
eq('no function key', array_key_exists('function', $schema), false);

// clickable forzato dall'argomento
$schema = TableColumn::key('visible')->visibleBadge(true)->toArray();
eq('visibleBadge preset', $schema['badge']['preset'], 'visible');
eq('visibleBadge clickable forced', $schema['badge']['clickable'], true);

$schema = TableColumn::key('evidence')->evidenceBadge()->toArray();
eq('evidenceBadge preset', $schema['badge']['preset'], 'evidence');

// generico: preset null, colonna default = key, fluent on/off/variant/clickable
$schema = TableColumn::key('stato')->booleanBadge()
    ->badgeOn('Aperto', 'bi bi-unlock', 'success', 'Chiudi')
    ->badgeOff('Chiuso', 'bi bi-lock', 'danger', 'Apri')
    ->badgeVariant('badge')
    ->badgeClickable()
    ->toArray();
eq('booleanBadge descriptor', $schema['badge'], [
    'preset' => null, 'column' => 'stato', 'variant' => 'badge', 'clickable' => true,
    'on' => ['text' => 'Aperto', 'icon' => 'bi bi-unlock', 'color' => 'success', 'button' => 'Chiudi'],
    'off' => ['text' => 'Chiuso', 'icon' => 'bi bi-lock', 'color' => 'danger', 'button' => 'Apri'],
]);

// colonna esplicita diversa dalla key
$schema = TableColumn::key('stato_label')->booleanBadge('stato')->toArray();
eq('booleanBadge explicit column', $schema['badge']['column'], 'stato');

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/App/ResourceSchema/TableColumnBadgeTest.php`
Expected: errore `Call to undefined method ...::activeBadge()`.

- [ ] **Step 3: Aggiungi i metodi a `TableColumn`**

In `class/App/ResourceSchema/TableColumn.php`, dopo il metodo `status()` (riga ~47), aggiungi:

```php
    public function activeBadge(bool $clickable = false): self
    {
        return $this->presetBadge('active', $clickable);
    }

    public function visibleBadge(bool $clickable = false): self
    {
        return $this->presetBadge('visible', $clickable);
    }

    public function evidenceBadge(bool $clickable = false): self
    {
        return $this->presetBadge('evidence', $clickable);
    }

    public function booleanBadge(?string $column = null): self
    {
        $this->setType('badge');

        return $this->schema('badge', [
            'preset' => null,
            'column' => $column ?? $this->name,
            'variant' => 'automaticResize',
            'clickable' => false,
        ]);
    }

    public function badgeOn(string $text, string $icon = '', string $color = '', string $buttonText = ''): self
    {
        return $this->mergeBadge('on', [
            'text' => $text, 'icon' => $icon, 'color' => $color, 'button' => $buttonText,
        ]);
    }

    public function badgeOff(string $text, string $icon = '', string $color = '', string $buttonText = ''): self
    {
        return $this->mergeBadge('off', [
            'text' => $text, 'icon' => $icon, 'color' => $color, 'button' => $buttonText,
        ]);
    }

    public function badgeVariant(string $variant): self
    {
        return $this->mergeBadge('variant', trim($variant));
    }

    public function badgeClickable(bool $clickable = true): self
    {
        return $this->mergeBadge('clickable', $clickable);
    }

    private function presetBadge(string $preset, bool $clickable): self
    {
        $this->setType('badge');

        return $this->schema('badge', [
            'preset' => $preset,
            'column' => $this->name,
            'variant' => 'automaticResize',
            'clickable' => $clickable,
        ]);
    }

    private function mergeBadge(string $key, mixed $value): self
    {
        $badge = (array) ($this->schema['badge'] ?? [
            'preset' => null,
            'column' => $this->name,
            'variant' => 'automaticResize',
            'clickable' => false,
        ]);

        $badge[$key] = $value;

        return $this->schema('badge', $badge);
    }
```

- [ ] **Step 4: Lint + test verdi**

Run: `php -l class/App/ResourceSchema/TableColumn.php && php tests/App/ResourceSchema/TableColumnBadgeTest.php`
Expected: `ALL PASS`, exit 0.

- [ ] **Step 5: Commit**

```bash
git add class/App/ResourceSchema/TableColumn.php tests/App/ResourceSchema/TableColumnBadgeTest.php
git commit -m "feat(table): declarative boolean badge API on TableColumn

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: risoluzione badge in `Field` + passthrough in `ResourceTableRenderer`

**Files:**
- Modify: `class/Backend/Table/Field.php` (metodo `setValue`, riga ~533; nuovo metodo privato `setBadgeValue`)
- Modify: `class/Backend/Support/ResourceTableRenderer.php` (metodo `legacyColumnFormat`, righe 237-256)
- Test: `tests/Backend/Table/FieldBadgeTest.php`

**Interfaces:**
- Consumes: `BooleanBadge::preset()/make()/on()/off()/action()/clickable()/render()` (Task 1); descrittore `badge` (Task 3); contesto iniettato di `Field` (`$this->table->name`, `$this->table->database`, `$this->link->api`, `$this->rowId`, `$this->row`, `$this->column`); `$this->ajaxRequest($url, $key)` esistente.
- Produces: celle badge renderizzate senza global; `$format['badge']` propagato dal renderer (usato anche dal Task 5).

Nota: nel blocco `# Set format` di `setValue` non esiste un ramo per il tipo `badge`, quindi l'HTML prodotto passa intatto (verificato: i rami sono solo image/date/datetime/phone/price/color/status/user*).

- [ ] **Step 1: Scrivi il test fallente**

Crea `tests/Backend/Table/FieldBadgeTest.php`:

```php
<?php
/** php tests/Backend/Table/FieldBadgeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\Field;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

function makeField(string $tableName = 'event'): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => $tableName, 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10, 'link' => [],
    ];
    $PATH = (object) [ 'site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api' ];
    $TEXT = (object) [
        'titleS' => 'evento', 'titleP' => 'eventi', 'last' => 'ultimi', 'all' => 'tutti',
        'article' => 'gli', 'full' => 'pieno', 'empty' => 'vuoto', 'this' => 'questo',
    ];
    $USER = (object) [ 'area' => '', 'authority' => '' ];
    $PAGE = (object) [ 'redirect' => '', 'redirectBase64' => '' ];
    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

$expectedOn = "<span class='badge text-bg-success'><span class='pc-none'><i class='bi bi-check-circle'></i></span><span class='phone-none'>ABILITATO</span></span>";

// descrittore preset: render col contesto iniettato, nessun global letto
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => false]]
);
eq('preset badge renders', $got, $expectedOn);

// non cliccabile di default: nessun onclick nell'output
eq('no onclick by default', str_contains((string) $got, 'onclick'), false);

// clickable forzato: onclick con table e id iniettati (endpoint change/boolean)
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => true]]
);
eq('clickable has onclick', str_contains((string) $got, 'onclick'), true);
eq('clickable url has table', str_contains((string) $got, 'table=event'), true);
eq('clickable url has id', str_contains((string) $got, 'id=7'), true);
eq('clickable url has column', str_contains((string) $got, 'column=active'), true);

// badge generico con on/off custom
$field = makeField();
$got = $field->newField(
    ['id' => 3, 'stato' => 'false'],
    'stato',
    ['format' => 'badge', 'badge' => [
        'preset' => null, 'column' => 'stato', 'variant' => 'badge', 'clickable' => false,
        'on' => ['text' => 'Aperto', 'icon' => '', 'color' => 'success', 'button' => ''],
        'off' => ['text' => 'Chiuso', 'icon' => '', 'color' => 'danger', 'button' => ''],
    ]]
);
eq('generic badge off', $got, "<span class='badge text-bg-danger'>CHIUSO</span>");

// descrittore malformato: cella vuota, nessun errore
$field = makeField();
$got = $field->newField(
    ['id' => 3, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'ghost', 'column' => 'active']]
);
eq('unknown preset renders empty', $got, '');

// special-case tabella user: badge nascosto per utenti multi-area+authority
$field = makeField('user');
$got = $field->newField(
    ['id' => 2, 'active' => 'true', 'area' => '["a","b"]', 'authority' => '["x","y"]'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => false]]
);
eq('user multi area+authority hidden', $got, '');

$field = makeField('user');
$got = $field->newField(
    ['id' => 2, 'active' => 'true', 'area' => '["a"]', 'authority' => '["x","y"]'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => false]]
);
eq('user single area shown', $got, $expectedOn);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Backend/Table/FieldBadgeTest.php`
Expected: FAIL — il descrittore `badge` viene ignorato, `$VALUE` resta il valore colonna (`"true"`).

- [ ] **Step 3: Implementa il ramo badge in `Field::setValue` + `badgeCell`**

In `class/Backend/Table/Field.php`, dentro `setValue()`, subito PRIMA del commento `# Set value from function` (riga ~533), aggiungi:

```php
            # Render badge booleano (API dichiarativa, contesto iniettato — mai global)
                if (isset($format['badge']) && is_array($format['badge'])) {

                    return $this->setBadgeValue($format, $COLUMN_VALUE);

                }
```

Attenzione: `setValue` prosegue con i blocchi `# Set format` e `# Set link to value`; per il badge nessuno dei due deve intervenire (il tipo `badge` non ha ramo in `# Set format` e un badge non ha `href`), quindi il `return` anticipato è corretto e mantiene il resto del metodo intatto. Per simmetria col flusso esistente, `setBadgeValue` restituisce direttamente l'HTML.

Sempre in `Field`, aggiungi questo metodo privato (dopo `setValue`, prima della chiusura della classe):

```php
        private function setBadgeValue($format, $COLUMN_VALUE) {

            $descriptor = $format['badge'];

            $preset = isset($descriptor['preset']) && is_string($descriptor['preset']) ? trim($descriptor['preset']) : '';
            $column = isset($descriptor['column']) && is_string($descriptor['column']) && trim($descriptor['column']) !== ''
                ? trim($descriptor['column'])
                : $this->column;
            $variant = isset($descriptor['variant']) && is_string($descriptor['variant']) && trim($descriptor['variant']) !== ''
                ? trim($descriptor['variant'])
                : 'automaticResize';

            $value = (is_array($this->row) && array_key_exists($column, $this->row)) ? $this->row[$column] : $COLUMN_VALUE;

            if ($preset !== '') {

                # Special-case tabella user: badge mostrato solo per utenti con al
                # massimo una area e una authority (parità col menu azioni).
                if ($this->table->name == 'user') {

                    $areas = json_decode($this->row['area'] ?? '[]', true);
                    $authorities = json_decode($this->row['authority'] ?? '[]', true);

                    if (is_array($areas) && is_array($authorities) && count($areas) > 1 && count($authorities) > 1) {
                        return '';
                    }

                }

                $badge = \Wonder\Backend\Table\Badge\BooleanBadge::preset($preset, $value);

                if ($badge === null) { return ''; }

            } else {

                $on = (array) ($descriptor['on'] ?? []);
                $off = (array) ($descriptor['off'] ?? []);

                $badge = \Wonder\Backend\Table\Badge\BooleanBadge::make($value)
                    ->on((string) ($on['text'] ?? ''), (string) ($on['icon'] ?? ''), (string) ($on['color'] ?? ''), (string) ($on['button'] ?? ''))
                    ->off((string) ($off['text'] ?? ''), (string) ($off['icon'] ?? ''), (string) ($off['color'] ?? ''), (string) ($off['button'] ?? ''));

            }

            $badge->action($this->ajaxRequest("{$this->link->api}/backend/change/boolean/", [ 'column' => $column ]));

            if (!empty($descriptor['clickable'])) { $badge->clickable(); }

            return $badge->render($variant);

        }
```

- [ ] **Step 4: Passthrough del descrittore in `ResourceTableRenderer`**

In `class/Backend/Support/ResourceTableRenderer.php`, metodo `legacyColumnFormat()` (riga ~237), dopo il blocco che copia `function`, aggiungi:

```php
        if (isset($config['badge']) && is_array($config['badge'])) {
            $format['badge'] = $config['badge'];
        }
```

- [ ] **Step 5: Lint + test verdi**

Run: `php -l class/Backend/Table/Field.php && php -l class/Backend/Support/ResourceTableRenderer.php && php tests/Backend/Table/FieldBadgeTest.php`
Expected: `ALL PASS`, exit 0.

- [ ] **Step 6: Regressione test esistenti**

Run: `php tests/Backend/Table/SearchFieldResolverTest.php && php tests/Backend/Table/SearchWhereTest.php`
Expected: `ALL PASS` su entrambi.

- [ ] **Step 7: Commit**

```bash
git add class/Backend/Table/Field.php class/Backend/Support/ResourceTableRenderer.php tests/Backend/Table/FieldBadgeTest.php
git commit -m "feat(table): resolve badge descriptors in Field with injected context

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: rimappatura legacy `function(active|visible|evidence)` → badge

**Files:**
- Modify: `class/Backend/Table/Field.php` (metodo `setValue`: rimappatura prima del ramo badge; rimozione del ramo legacy `active|visible|evidence` a riga ~562)
- Test: `tests/Backend/Table/FieldBadgeLegacyRemapTest.php`

**Interfaces:**
- Consumes: ramo badge + `setBadgeValue` (Task 4).
- Produces: il warning `Attempt to read property "table" on null` sparisce per ogni sito/modulo che usa ancora `->function('active', 'id', 'automaticResize')` (es. `EventResource` del modulo rsvp), senza modificarli.

- [ ] **Step 1: Scrivi il test fallente**

Crea `tests/Backend/Table/FieldBadgeLegacyRemapTest.php`:

```php
<?php
/** php tests/Backend/Table/FieldBadgeLegacyRemapTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\Field;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

function makeField(string $tableName = 'event'): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => $tableName, 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10, 'link' => [],
    ];
    $PATH = (object) [ 'site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api' ];
    $TEXT = (object) [
        'titleS' => 'evento', 'titleP' => 'eventi', 'last' => 'ultimi', 'all' => 'tutti',
        'article' => 'gli', 'full' => 'pieno', 'empty' => 'vuoto', 'this' => 'questo',
    ];
    $USER = (object) [ 'area' => '', 'authority' => '' ];
    $PAGE = (object) [ 'redirect' => '', 'redirectBase64' => '' ];
    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

// Nessun global $NAME/$PATH definito: il percorso legacy deve comunque
// renderizzare senza warning. Convertiamo i warning in eccezioni per
// intercettare regressioni.
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$expectedOn = "<span class='badge text-bg-success'><span class='pc-none'><i class='bi bi-check-circle'></i></span><span class='phone-none'>ABILITATO</span></span>";

// il caso esatto del bug: TableColumn::key('active')->badge()->function('active', 'id', 'automaticResize')
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'function' => ['name' => 'active', 'parameter' => 'id', 'return' => 'automaticResize']]
);
eq('legacy active remapped, no warning', $got, $expectedOn);
eq('legacy remap not clickable', str_contains((string) $got, 'onclick'), false);

// visible ed evidence
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'visible' => 'false'],
    'visible',
    ['format' => 'badge', 'function' => ['name' => 'visible', 'parameter' => 'id', 'return' => 'automaticResize']]
);
eq('legacy visible remapped', $got,
    "<span class='badge text-bg-danger'><span class='pc-none'><i class='bi bi-eye-slash'></i></span><span class='phone-none'>NASCOSTO</span></span>"
);

$field = makeField();
$got = $field->newField(
    ['id' => 7, 'evidence' => 'false'],
    'evidence',
    ['format' => 'badge', 'function' => ['name' => 'evidence', 'parameter' => 'id', 'return' => 'automaticResize']]
);
eq('legacy evidence off remapped empty', $got, '');

// return mancante → default automaticResize
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'function' => ['name' => 'active', 'parameter' => 'id', 'return' => null]]
);
eq('legacy remap default variant', $got, $expectedOn);

// special-case user preservata anche sul percorso legacy
$field = makeField('user');
$got = $field->newField(
    ['id' => 2, 'active' => 'true', 'area' => '["a","b"]', 'authority' => '["x","y"]'],
    'active',
    ['format' => 'badge', 'function' => ['name' => 'active', 'parameter' => 'id', 'return' => 'automaticResize']]
);
eq('legacy remap user gate', $got, '');

restore_error_handler();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Backend/Table/FieldBadgeLegacyRemapTest.php`
Expected: FAIL/eccezione — il ramo legacy chiama `active()` (funzione non definita nel test standalone) oppure, a runtime completo, legge `global $NAME` null.

- [ ] **Step 3: Implementa la rimappatura e rimuovi il ramo legacy**

In `class/Backend/Table/Field.php`, dentro `setValue()`, SOPRA il blocco badge aggiunto nel Task 4, inserisci:

```php
            # Rimappa i descrittori legacy function(active|visible|evidence) sul
            # percorso badge: niente funzioni globali, niente global $NAME/$PATH.
                if (
                    isset($format['function']['name'])
                    && in_array($format['function']['name'], ['active', 'visible', 'evidence'], true)
                ) {

                    $format['badge'] = [
                        'preset' => $format['function']['name'],
                        'column' => $this->column,
                        'variant' => (isset($format['function']['return']) && !empty($format['function']['return']))
                            ? $format['function']['return']
                            : 'automaticResize',
                        'clickable' => false,
                    ];

                    unset($format['function']);

                }
```

Poi, nel blocco `# Set value from function`, ELIMINA integralmente il ramo ormai irraggiungibile (righe ~562-573):

```php
                    } else if ($functionName == "active" || $functionName == "visible" || $functionName == "evidence") {

                        $functionReturn = $format['function']['return'];

                        if ($this->table->name == 'user') {
                            if (count(json_decode($this->row['area'], true)) <= 1 || count(json_decode($this->row['authority'], true)) <= 1) {
                                $VALUE = call_user_func_array($functionName, [$COLUMN_VALUE, $this->rowId])->$functionReturn; 
                            }
                        } else {
                            $VALUE = call_user_func_array($functionName, [$COLUMN_VALUE, $this->rowId])->$functionReturn; 
                        }

                    } else {
```

sostituendolo con la sola continuazione `} else {`.

- [ ] **Step 4: Lint + test verdi (nuovo + Task 4 + regressione)**

Run: `php -l class/Backend/Table/Field.php && php tests/Backend/Table/FieldBadgeLegacyRemapTest.php && php tests/Backend/Table/FieldBadgeTest.php && php tests/Backend/Table/SearchFieldResolverTest.php && php tests/Backend/Table/SearchWhereTest.php`
Expected: `ALL PASS` su tutti.

- [ ] **Step 5: Commit**

```bash
git add class/Backend/Table/Field.php tests/Backend/Table/FieldBadgeLegacyRemapTest.php
git commit -m "fix(table): remap legacy active/visible/evidence functions to badge path

Fixes 'Attempt to read property table on null' in plugin.php during
Resource table prerender and AJAX list rendering.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: whitelist `call_user_func` in `Field`

**Files:**
- Modify: `class/Backend/Table/Field.php` (ramo generico di `# Set value from function`, riga ~574 post-Task 5)
- Test: `tests/Backend/Table/FieldFunctionWhitelistTest.php`

**Interfaces:**
- Consumes: `ColumnFunctionRegistry::isAllowed()` / `::allow()` (Task 2).
- Produces: nessuna chiamata a funzioni non dichiarate server-side; cella vuota per nomi arbitrari dal POST.

- [ ] **Step 1: Scrivi il test fallente**

Crea `tests/Backend/Table/FieldFunctionWhitelistTest.php`:

```php
<?php
/** php tests/Backend/Table/FieldFunctionWhitelistTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\Field;
use Wonder\Backend\Table\ColumnFunctionRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

function makeField(): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => 'event', 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10, 'link' => [],
    ];
    $PATH = (object) [ 'site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api' ];
    $TEXT = (object) [
        'titleS' => 'evento', 'titleP' => 'eventi', 'last' => 'ultimi', 'all' => 'tutti',
        'article' => 'gli', 'full' => 'pieno', 'empty' => 'vuoto', 'this' => 'questo',
    ];
    $USER = (object) [ 'area' => '', 'authority' => '' ];
    $PAGE = (object) [ 'redirect' => '', 'redirectBase64' => '' ];
    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

// funzione "di sito" legittima, registrata esplicitamente
function wi_test_column_fn($v) { return "X-$v"; }

ColumnFunctionRegistry::reset();

// nome NON in whitelist (una funzione PHP qualunque): cella vuota, nessuna esecuzione
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'name' => 'hello'],
    'name',
    ['format' => 'text', 'function' => ['name' => 'strtoupper', 'parameter' => 'name', 'return' => null]]
);
eq('non-whitelisted function blocked', $got, '');

// stesso nome dopo allow(): eseguita
ColumnFunctionRegistry::allow('wi_test_column_fn');
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'name' => 'hello'],
    'name',
    ['format' => 'text', 'function' => ['name' => 'wi_test_column_fn', 'parameter' => 'name', 'return' => null]]
);
eq('whitelisted function runs', $got, 'X-hello');

// nome in whitelist ma funzione inesistente: cella vuota, nessun fatal
ColumnFunctionRegistry::allow('wi_test_missing_fn');
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'name' => 'hello'],
    'name',
    ['format' => 'text', 'function' => ['name' => 'wi_test_missing_fn', 'parameter' => 'name', 'return' => null]]
);
eq('missing function renders empty', $got, '');

ColumnFunctionRegistry::reset();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Backend/Table/FieldFunctionWhitelistTest.php`
Expected: FAIL — `strtoupper` viene eseguita (`"HELLO"` invece di `""`).

- [ ] **Step 3: Implementa il check nel ramo generico**

In `class/Backend/Table/Field.php`, nel blocco `# Set value from function`, il ramo finale `} else {` (quello che oggi inizia con il commento `# Controllo se è già stata chiamata questa funzione...`) va aperto così:

```php
                    } else {

                        # Whitelist server-side: il nome arriva dal POST di
                        # list-table, senza controllo sarebbe una chiamata a
                        # funzione PHP arbitraria.
                        if (
                            !\Wonder\Backend\Table\ColumnFunctionRegistry::isAllowed($functionName)
                            || !function_exists($functionName)
                        ) {

                            $VALUE = '';

                        } else if (isset($this->function[$functionName]) && $this->function[$functionName]['parameter'] == $functionParameter) {
```

Il resto del ramo (cache `$this->function`, costruzione `$args`, `call_user_func_array`) resta invariato: cambia solo l'aggiunta del primo `if` e la trasformazione dell'`if` esistente in `else if`.

- [ ] **Step 4: Lint + test verdi (nuovo + tutti i precedenti)**

Run: `php -l class/Backend/Table/Field.php && php tests/Backend/Table/FieldFunctionWhitelistTest.php && php tests/Backend/Table/FieldBadgeTest.php && php tests/Backend/Table/FieldBadgeLegacyRemapTest.php`
Expected: `ALL PASS` su tutti.

- [ ] **Step 5: Commit**

```bash
git add class/Backend/Table/Field.php tests/Backend/Table/FieldFunctionWhitelistTest.php
git commit -m "fix(table): enforce server-side whitelist on column render functions

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: delega di `changeActive/changeVisible/changeEvidence` + wrapper deprecati in `plugin.php`

**Files:**
- Modify: `class/Backend/Table/Field.php` (metodi `changeVisible` ~riga 218, `changeActive` ~riga 244, `changeEvidence` ~riga 270)
- Modify: `app/function/backend/plugin.php` (funzioni `returnBadge` riga 3, `visible` riga 47, `active` riga 79, `evidence` riga 108)
- Test: `tests/Backend/PluginBadgeWrappersTest.php`

**Interfaces:**
- Consumes: `BooleanBadge` preset + `legacyObject()` (Task 1); `LegacyGlobals::get('NAME'|'PATH')` (esistente).
- Produces: oggetti con shape legacy invariata (`->badge`, `->automaticResize`, `->button`, `->action`, ...) sia dal menu azioni di `Field` sia dalle funzioni globali; nessun warning quando `$NAME` è assente.

- [ ] **Step 1: Scrivi il test fallente**

Crea `tests/Backend/PluginBadgeWrappersTest.php`:

```php
<?php
/** php tests/Backend/PluginBadgeWrappersTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../app/function/backend/plugin.php';

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

// I warning diventano eccezioni: se un wrapper legge un global null, il test fallisce.
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- senza global $NAME/$PATH: nessun warning, action vuota, badge corretto
$obj = active('true', 5);
eq('active no-globals automaticResize', $obj->automaticResize,
    "<span class='badge text-bg-success'><span class='pc-none'><i class='bi bi-check-circle'></i></span><span class='phone-none'>ABILITATO</span></span>"
);
eq('active no-globals action empty', $obj->action, '');
eq('active no-globals button empty', $obj->button, '');

$obj = visible('false', 5);
eq('visible off text', $obj->text, 'Nascosto');
eq('visible off badge', $obj->badge, "<span class='badge text-bg-danger'>NASCOSTO</span>");

$obj = evidence('false', 5);
eq('evidence off automaticResize empty', $obj->automaticResize, '');

// --- con i global popolati (come nelle pagine legacy): action e button costruiti
\Wonder\App\LegacyGlobals::set('NAME', (object) [ 'table' => 'event' ]);
\Wonder\App\LegacyGlobals::set('PATH', (object) [ 'api' => '/api' ]);

$obj = active('true', 5);
eq('active with globals action',
    $obj->action,
    "onclick=\"ajaxRequest('/api/backend/active/?table=event&id=5')\""
);
eq('active with globals button',
    $obj->button,
    "<a class='dropdown-item ' onclick=\"ajaxRequest('/api/backend/active/?table=event&id=5')\" role='button'>Disabilita</a>"
);

$obj = visible('true', 9);
eq('visible with globals action',
    $obj->action,
    "onclick=\"ajaxRequest('/api/backend/visible/?table=event&id=9')\""
);

$obj = evidence('true', 3);
eq('evidence with globals action',
    $obj->action,
    "onclick=\"ajaxRequest('/api/backend/change/boolean/?table=event&column=evidence&id=3')\""
);

// --- returnBadge resta utilizzabile direttamente
$obj = returnBadge('Testo', 'bi bi-star', 'warning');
eq('returnBadge badge', $obj->badge, "<span class='badge text-bg-warning'>TESTO</span>");
eq('returnBadge tooltip', $obj->tooltip, "<i class='bi bi-star' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Testo'></i>");

restore_error_handler();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Backend/PluginBadgeWrappersTest.php`
Expected: FAIL con `ErrorException: Attempt to read property "table" on null` (il bug originale, riprodotto).

- [ ] **Step 3: Riscrivi i wrapper in `plugin.php`**

In `app/function/backend/plugin.php` sostituisci integralmente le funzioni `returnBadge` (righe 3-20), `visible` (47-77), `active` (79-106), `evidence` (108-135) con:

```php
    /**
     * @deprecated Usare Wonder\Backend\Table\Badge\BooleanBadge.
     */
    function returnBadge($text, $classIcon, $bootstrapColor) {

        return \Wonder\Backend\Table\Badge\BooleanBadge::make(true)
            ->on((string) $text, (string) $classIcon, (string) $bootstrapColor)
            ->legacyObject();

    }

    /**
     * Contesto tabella per le action legacy: legge i global via LegacyGlobals
     * con fallback safe (niente warning se il contesto manca, es. nel flusso
     * Resource dove il render passa da Field con contesto iniettato).
     */
    function legacyTableContext(): ?object {

        $NAME = \Wonder\App\LegacyGlobals::get('NAME');
        $PATH = \Wonder\App\LegacyGlobals::get('PATH');

        if (!is_object($NAME) || empty($NAME->table) || !is_object($PATH) || empty($PATH->api)) {
            return null;
        }

        return (object) [ 'table' => $NAME->table, 'api' => $PATH->api ];

    }

    /**
     * @deprecated Usare TableColumn::visibleBadge() / BooleanBadge::visible().
     */
    function visible($visible, $id) {

        $ctx = legacyTableContext();
        $action = $ctx === null ? '' : "onclick=\"ajaxRequest('{$ctx->api}/backend/visible/?table={$ctx->table}&id=$id')\"";

        return \Wonder\Backend\Table\Badge\BooleanBadge::visible($visible)->action($action)->legacyObject();

    }

    /**
     * @deprecated Usare TableColumn::activeBadge() / BooleanBadge::active().
     */
    function active($active, $id) {

        $ctx = legacyTableContext();
        $action = $ctx === null ? '' : "onclick=\"ajaxRequest('{$ctx->api}/backend/active/?table={$ctx->table}&id=$id')\"";

        return \Wonder\Backend\Table\Badge\BooleanBadge::active($active)->action($action)->legacyObject();

    }

    /**
     * @deprecated Usare TableColumn::evidenceBadge() / BooleanBadge::evidence().
     */
    function evidence($evidence, $id) {

        $ctx = legacyTableContext();
        $action = $ctx === null ? '' : "onclick=\"ajaxRequest('{$ctx->api}/backend/change/boolean/?table={$ctx->table}&column=evidence&id=$id')\"";

        return \Wonder\Backend\Table\Badge\BooleanBadge::evidence($evidence)->action($action)->legacyObject();

    }
```

`returnButton`, `createAddButton`, `delete`, `removeAuthorization`, `isEmpty` restano invariati.

- [ ] **Step 4: Delega i metodi `change*` di `Field` ai preset**

In `class/Backend/Table/Field.php` sostituisci integralmente i tre metodi:

```php
        private function changeVisible() {

            $action = $this->ajaxRequest("{$this->link->api}/backend/change/boolean/", [ 'column' => 'visible' ]);

            return \Wonder\Backend\Table\Badge\BooleanBadge::visible($this->row['visible'] ?? '')
                ->action($action)
                ->legacyObject();

        }

        private function changeActive() {

            $action = $this->ajaxRequest("{$this->link->api}/backend/change/boolean/", [ 'column' => 'active' ]);

            return \Wonder\Backend\Table\Badge\BooleanBadge::active($this->row['active'] ?? '')
                ->action($action)
                ->legacyObject();

        }

        private function changeEvidence() {

            $action = $this->ajaxRequest("{$this->link->api}/backend/change/boolean/", [ 'column' => 'evidence' ]);

            return \Wonder\Backend\Table\Badge\BooleanBadge::evidence($this->row['evidence'] ?? '')
                ->action($action)
                ->legacyObject();

        }
```

Nota comportamentale accettata: `changeEvidence` legacy in stato off aveva `text = ""` (nessun badge), il preset è identico. `changeActive`/`changeVisible` sono byte-identici. `legacyObject()->button` replica `actionButtonItem($textButton, $action)` = `returnButton($text, $action)` senza colore.

- [ ] **Step 5: Lint + test verdi (nuovo + tutti)**

Run: `php -l app/function/backend/plugin.php && php -l class/Backend/Table/Field.php && php tests/Backend/PluginBadgeWrappersTest.php && php tests/Backend/Table/FieldBadgeTest.php && php tests/Backend/Table/FieldBadgeLegacyRemapTest.php && php tests/Backend/Table/FieldFunctionWhitelistTest.php && php tests/Backend/Table/Badge/BooleanBadgeTest.php`
Expected: `ALL PASS` su tutti.

- [ ] **Step 6: Commit**

```bash
git add app/function/backend/plugin.php class/Backend/Table/Field.php tests/Backend/PluginBadgeWrappersTest.php
git commit -m "refactor(table): delegate legacy badge functions to BooleanBadge

active()/visible()/evidence()/returnBadge() become deprecated wrappers
with safe LegacyGlobals fallback; Field action-menu builders reuse the
same presets (single source of truth).

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: migrazione delle Resource del framework ai nuovi helper

**Files:**
- Modify: `class/App/Resources/Support/UserManagementResource.php:94`
- Modify: `class/App/Resources/Config/LegalDocumentResource.php:100`
- Modify: `class/App/Resources/Css/CssFontResource.php:96-99`
- Modify: `class/App/Resources/Communications/PopupResource.php:143-146`
- Modify: `class/App/Resources/Communications/AnnouncementResource.php:95-98`

**Interfaces:**
- Consumes: `TableColumn::activeBadge()` / `visibleBadge()` (Task 3).
- Produces: il framework usa la nuova API (esempio per siti e moduli). Nessuna nuova interfaccia.

- [ ] **Step 1: Applica le sostituzioni**

`UserManagementResource.php` riga 94 — da:
```php
            TableColumn::key('active')->badge()->function('active', 'id', 'automaticResize')->size('little'),
```
a:
```php
            TableColumn::key('active')->activeBadge()->size('little'),
```

`LegalDocumentResource.php` riga 100 — stessa identica sostituzione.

`CssFontResource.php` righe 96-99, `PopupResource.php` righe 143-146, `AnnouncementResource.php` righe 95-98 — da:
```php
            TableColumn::key('visible')
                ->badge()
                ->function('visible', 'id', 'automaticResize')
                ->size('little'),
```
a:
```php
            TableColumn::key('visible')
                ->visibleBadge()
                ->size('little'),
```

Non toccare la riga `authority` di `UserManagementResource` (usa `permissionsFunction()`, gestita dal ramo `permissions*`).

- [ ] **Step 2: Lint**

Run: `for f in class/App/Resources/Support/UserManagementResource.php class/App/Resources/Config/LegalDocumentResource.php class/App/Resources/Css/CssFontResource.php class/App/Resources/Communications/PopupResource.php class/App/Resources/Communications/AnnouncementResource.php; do php -l $f || exit 1; done`
Expected: `No syntax errors` ×5.

- [ ] **Step 3: Verifica che non restino usi interni del percorso legacy**

Run: `grep -rn "function('active'\|function('visible'\|function('evidence'" class/`
Expected: nessun risultato.

- [ ] **Step 4: Commit**

```bash
git add class/App/Resources
git commit -m "refactor(resources): migrate framework resources to activeBadge/visibleBadge

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 9: documentazione

**Files:**
- Modify: `docs/app/concetti/tabelle/tablecolumn.md`
- Modify: `docs/app/concetti/tabelle/opzioni-colonna.md`
- Modify: `docs/app/concetti/tabelle/legacy.md`

**Interfaces:** nessuna (solo docs). Richiesta esplicita dell'utente: la documentazione va aggiornata nello stesso lavoro.

- [ ] **Step 1: `tablecolumn.md` — nuova sezione dopo "### Azioni"**

Inserisci dopo la sezione `### Azioni` (riga ~38):

```markdown
### Badge booleani

Per le colonne booleane `active` / `visible` / `evidence` esistono helper
dedicati che sostituiscono il vecchio `->badge()->function('active', ...)`:

```php
TableColumn::key('active')->activeBadge()->size('little'),
TableColumn::key('visible')->visibleBadge(),
TableColumn::key('evidence')->evidenceBadge(),
```

Il badge è **statico**: il toggle resta nel menu azioni della riga
(`->actions(['visible', ...])`). Per forzare il toggle direttamente sul badge
(scelta esplicita, non il default): `->activeBadge(true)` oppure
`->badgeClickable()`.

Per badge booleani custom c'è la base generica:

```php
TableColumn::key('stato')->booleanBadge()
    ->badgeOn('Aperto', 'bi bi-unlock', 'success', 'Chiudi')
    ->badgeOff('Chiuso', 'bi bi-lock', 'danger', 'Apri')
    ->badgeVariant('automaticResize')   // default; anche: badge, tooltip, badgeTooltip, icon
    ->badgeClickable(),                 // opzionale, opt-in
```

`booleanBadge('altra_colonna')` legge il valore da una colonna diversa dalla
key. Il render passa da `Wonder\Backend\Table\Badge\BooleanBadge`
(`class/Backend/Table/Badge/BooleanBadge.php`), che è anche l'API da usare
per renderizzare questi badge fuori dagli schema. Il valore è "on" solo se
`'true'`/`true`.
```

- [ ] **Step 2: `opzioni-colonna.md` — aggiorna la sezione `function`**

Nella sezione `## function — valore calcolato` (riga ~46): sostituisci l'esempio `->function('visible', 'id', 'automaticResize')` (riga ~51) e quello a riga ~93 con `->visibleBadge()`, e aggiungi subito dopo la firma (riga ~54):

```markdown
> **Deprecato per i booleani:** `function('active'|'visible'|'evidence', ...)`
> è rimappato internamente sui badge booleani (vedi
> [TableColumn](tablecolumn.md#badge-booleani)) e continua a funzionare, ma i
> nuovi schema devono usare `activeBadge()` / `visibleBadge()` /
> `evidenceBadge()`.
>
> **Whitelist:** per gli altri nomi, la funzione viene eseguita solo se
> dichiarata in uno schema registrato lato server o consentita via
> `Wonder\Backend\Table\ColumnFunctionRegistry::allow('nomeFunzione')`
> (necessario per le pagine legacy con funzioni custom). Nomi non dichiarati
> producono cella vuota.
```

- [ ] **Step 3: `legacy.md` — nota di deprecazione con mappa di migrazione**

Leggi il file e aggiungi in coda (o nella sezione dedicata alle funzioni se esiste):

```markdown
## Funzioni badge deprecate (plugin.php)

`active()`, `visible()`, `evidence()` e `returnBadge()` di
`app/function/backend/plugin.php` sono wrapper deprecati che delegano a
`Wonder\Backend\Table\Badge\BooleanBadge`. Se il global `$NAME` non è
popolato l'`action` risulta vuota (niente più warning). Mappa di migrazione:

| Legacy | Nuova API |
|---|---|
| `->badge()->function('active', 'id', 'automaticResize')` | `->activeBadge()` |
| `->badge()->function('visible', 'id', 'automaticResize')` | `->visibleBadge()` |
| `->badge()->function('evidence', 'id', 'automaticResize')` | `->evidenceBadge()` |
| `active($value, $id)->automaticResize` | `BooleanBadge::active($value)->automaticResize()` |
| `returnBadge($text, $icon, $color)->badge` | `BooleanBadge::make(true)->on($text, $icon, $color)->badge()` |
```

- [ ] **Step 4: Commit**

```bash
git add docs/app/concetti/tabelle
git commit -m "docs(table): document boolean badge API and legacy deprecations

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 10: validazione finale e integrazione da un sito

**Files:** nessuno (solo verifica).

- [ ] **Step 1: Suite completa**

Run: `for t in tests/Backend/Table/Badge/BooleanBadgeTest.php tests/Backend/Table/ColumnFunctionRegistryTest.php tests/App/ResourceSchema/TableColumnBadgeTest.php tests/Backend/Table/FieldBadgeTest.php tests/Backend/Table/FieldBadgeLegacyRemapTest.php tests/Backend/Table/FieldFunctionWhitelistTest.php tests/Backend/PluginBadgeWrappersTest.php tests/Backend/Table/SearchFieldResolverTest.php tests/Backend/Table/SearchWhereTest.php; do php $t || exit 1; done`
Expected: `ALL PASS` su tutti, exit 0.

- [ ] **Step 2: Lint globale dei file toccati**

Run: `git diff --name-only HEAD~9 -- '*.php' | xargs -n1 php -l`
Expected: `No syntax errors` per ogni file.

- [ ] **Step 3: Validazione da un sito (rsvp-site)**

Dal progetto `/Users/andreamarinoni/Developer/boilerplates/rsvp-site`:

```bash
php forge update --local
php forge start
```

Verifica manuale (o con l'utente):
1. La lista eventi del modulo rsvp (che usa `TableColumn::key('active')->badge()->function('active', 'id', return: 'automaticResize')`) non emette più il warning `Attempt to read property "table" on null` e mostra il badge ABILITATO/DISABILITATO.
2. Il badge non è cliccabile; il toggle da menu azioni funziona e ricarica la tabella.
3. Le liste framework migrate (utenti, popup, annunci, font CSS, documenti legali) mostrano badge identici a prima.
4. Ricerca, paginazione e pre-render (prima pagina senza chiamata AJAX) funzionano.

- [ ] **Step 4: Commit finale (se emergono fix dalla validazione)**

Solo se la validazione richiede correzioni; altrimenti nessun commit.
