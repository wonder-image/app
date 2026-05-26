<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\Backend\Support\BackendNavigationSections;

/**
 * DSL della voce di navigation per una singola Resource.
 *
 * Una Resource configura il proprio posizionamento nel menu chiamando
 * `for(static::class)` in `navigationSchema()` e chainando i setter.
 * Due ruoli possibili:
 *
 *  - **dichiarante**: invoca `section($key, $title, $icon, $order)`,
 *    che REGISTRA la sezione nel runtime registry
 *    `BackendNavigationSections` E attacca la Resource a quella key.
 *    Una sola Resource per sezione dovrebbe avere questo ruolo (le
 *    altre dovrebbero usare `inSection()`).
 *
 *  - **attaccante**: invoca `inSection($key)`, che dice "questa
 *    Resource appartiene alla sezione con questa key" senza dichiararne
 *    i metadati. La sezione deve essere stata registrata da una
 *    qualche Resource (anche dopo, perché la risoluzione è in due
 *    pass — vedi `BackendNavigation::resourceSections`).
 *
 * Una Resource che non chiama né `section()` né `inSection()` diventa
 * una sezione top-level standalone (no sub-menu).
 */
final class NavigationSchema
{
    private array $schema;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->schema = [
            'enabled' => true,

            # Key della sezione a cui la Resource appartiene (null se
            # standalone). Popolato da `section()` o `inSection()`.
            'section_key' => null,

            # Override esplicito dell'order della sezione top-level.
            # null = usa l'order dichiarato in `section()`. int = forza.
            # Tipicamente usato solo per Resource standalone.
            'section_order' => null,

            'title' => $this->resourceClass::titleLabel(),
            'order' => 100,
            'file' => 'list',
            'authority' => [],
        ];
    }

    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    public function enabled(bool $enabled = true): self
    {
        $this->schema['enabled'] = $enabled;

        return $this;
    }

    /**
     * DICHIARA e attacca la Resource alla sezione `$key`.
     *
     * Una sola Resource per sezione dovrebbe chiamare questo metodo —
     * tipicamente quella concettualmente "primaria" o quella con
     * `order` più basso. Le altre Resource della stessa sezione usano
     * `inSection($key)`.
     *
     * I metadati (`title`, `icon`, `order`, `authority`) vengono
     * registrati globalmente in `BackendNavigationSections`. Più
     * Resource possono dichiarare la stessa key SOLO se passano
     * metadati identici (registrazione idempotente). Conflitti →
     * eccezione esplicita al boot.
     *
     * @param string $key       identificatore (es. 'set-up', 'notices')
     * @param string $title     label mostrato nel menu (es. 'Set Up')
     * @param string $icon      classe Bootstrap Icons (es. 'bi-gear')
     * @param int    $order     ordinamento top-level. Convenzione:
     *                          0 = Home, 1-999 = sezioni custom,
     *                          1000+ = sezioni core.
     * @param array  $authority lista di permessi richiesti per
     *                          vedere la sezione.
     */
    public function section(
        string $key,
        string $title,
        string $icon,
        int $order = 500,
        array $authority = [],
    ): self {
        BackendNavigationSections::register($key, $title, $icon, $order, $authority);

        $this->schema['section_key'] = trim($key);

        if ($authority !== []) {
            $this->schema['authority'] = $authority;
        }

        return $this;
    }

    /**
     * Attacca la Resource a una sezione già dichiarata (o che verrà
     * dichiarata da un'altra Resource processata nello stesso boot).
     *
     * Non valida immediatamente: la verifica che la `key` sia
     * effettivamente registrata avviene in
     * `BackendNavigation::resourceSections()` dopo la PASS 1 di
     * collezione delle dichiarazioni.
     */
    public function inSection(string $key): self
    {
        $this->schema['section_key'] = trim($key);

        return $this;
    }

    public function title(string $title): self
    {
        $this->schema['title'] = trim($title);

        return $this;
    }

    /**
     * Ordine della voce DENTRO la sezione (subnav order).
     */
    public function order(int $order): self
    {
        $this->schema['order'] = $order;

        return $this;
    }

    /**
     * Override esplicito dell'order della sezione top-level. Utile
     * per Resource standalone (no `section()`, no `inSection()`)
     * per posizionarle al posto desiderato (default 500).
     *
     *   Home    →    0   (sempre prima)
     *   custom  →  500   (default per standalone)
     *   Media   → 1000   (convenzione: sezioni "core" sopra 1000)
     */
    public function sectionOrder(int $order): self
    {
        $this->schema['section_order'] = $order;

        return $this;
    }

    public function file(string $file): self
    {
        $this->schema['file'] = trim($file);

        return $this;
    }

    public function authority(array $authority): self
    {
        $this->schema['authority'] = $authority;

        return $this;
    }

    /**
     * Risolve la sezione di appartenenza in dati concreti.
     *
     * - `null` se la Resource è standalone (no section_key impostata)
     * - dati dal registry se la key è registrata
     * - `null` (silente) se la key è impostata ma non registrata. La
     *   validazione "errore se non registrata" è responsabilità di
     *   `BackendNavigation::resourceSections()`, che ha visibilità su
     *   TUTTE le Resource (questo metodo invece è per-singola Resource).
     *
     * @return array{key:string,title:string,folder:string,icon:string,order:int,authority:array}|null
     */
    public function resolveSection(): ?array
    {
        $key = $this->schema['section_key'] ?? null;

        if (!is_string($key) || $key === '') {
            return null;
        }

        $section = BackendNavigationSections::lookup($key);

        if ($section === null) {
            return null;
        }

        # Applica override esplicito dell'order della sezione se
        # presente (raro: la sezione di solito eredita l'order da
        # `section()`).
        if (is_int($this->schema['section_order'] ?? null)) {
            $section['order'] = (int) $this->schema['section_order'];
        }

        return $section;
    }

    public function toArray(): array
    {
        return $this->all();
    }

    public function get(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->schema;
        }

        return $this->schema[$key] ?? null;
    }

    public function all(): array
    {
        return $this->schema;
    }
}
