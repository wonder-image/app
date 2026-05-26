<?php

namespace Wonder\Backend\Support;

use RuntimeException;

/**
 * Runtime registry delle sezioni della navigation di backend.
 *
 * A differenza di una versione precedente (con una `registry()`
 * hardcoded), qui le sezioni vengono **dichiarate dalle Resource** al
 * boot della navigation: ogni Resource che chiama
 * `NavigationSchema::section(key, title, icon, order, authority)`
 * invoca `register()` qui, popolando il catalogo.
 *
 * Strategia conflitti — *errore esplicito*: se due Resource registrano
 * la stessa `key` con metadati diversi viene lanciata un'eccezione al
 * boot. Registrazioni con identica payload sono idempotenti (no-op).
 *
 * Quando NESSUNA Resource referenzia una key, la sezione semplicemente
 * non esiste — è l'invariante: "le nav sono create dalle Resource".
 */
final class BackendNavigationSections
{
    /**
     * Mappa `key => sezione` accumulata dalle chiamate a `register()`.
     *
     * Vive per la durata del processo PHP; in produzione viene
     * popolata una volta per request al primo accesso a
     * `BackendNavigation::all()`. Per test in isolation usare `reset()`.
     *
     * @var array<string, array{key:string,title:string,folder:string,icon:string,order:int,authority:array<int,string>}>
     */
    private static array $registered = [];

    /**
     * Registra (o conferma) una sezione. Idempotente quando i metadati
     * coincidono con una registrazione precedente; lancia eccezione se
     * la `key` esiste già con metadati DIVERSI — è un errore di
     * configurazione delle Resource e va sistemato in fase di sviluppo.
     *
     * `folder` di default coincide con `key`. Override raro: utile se
     * la URL della sezione differisce dalla key (es. legacy paths).
     */
    public static function register(
        string $key,
        string $title,
        string $icon,
        int $order = 500,
        array $authority = [],
        ?string $folder = null,
    ): void {
        $normalizedKey = trim($key);

        if ($normalizedKey === '') {
            throw new RuntimeException('BackendNavigationSections::register(): key vuota.');
        }

        $record = [
            'key' => $normalizedKey,
            'title' => trim($title),
            'folder' => $folder !== null ? trim($folder) : $normalizedKey,
            'icon' => trim($icon),
            'order' => $order,
            'authority' => self::normalizeAuthority($authority),
        ];

        if (isset(self::$registered[$normalizedKey])) {
            $existing = self::$registered[$normalizedKey];

            if (self::sameRecord($existing, $record)) {
                return;
            }

            throw new RuntimeException(
                "Conflitto nella registrazione della sezione '{$normalizedKey}': "
                ."valori incompatibili. "
                ."Esistente: ".self::dumpRecord($existing).". "
                ."Nuovo: ".self::dumpRecord($record).". "
                ."Una sola Resource per sezione deve chiamare section() con i metadati."
            );
        }

        self::$registered[$normalizedKey] = $record;
    }

    /**
     * Ritorna i dati di una sezione registrata o `null` se non esiste.
     * Lookup case-insensitive su `key`, `folder` o `title`.
     */
    public static function lookup(string $keyOrFolderOrTitle): ?array
    {
        $needle = mb_strtolower(trim($keyOrFolderOrTitle));

        if ($needle === '') {
            return null;
        }

        foreach (self::$registered as $section) {
            $key = mb_strtolower($section['key']);
            $folder = mb_strtolower($section['folder']);
            $title = mb_strtolower($section['title']);

            if ($key === $needle || $folder === $needle || $title === $needle) {
                return $section;
            }
        }

        return null;
    }

    /**
     * `true` se la `key` è registrata. Wrapper conveniente per
     * validazione di `inSection()` references da
     * `BackendNavigation::resourceSections()`.
     */
    public static function has(string $key): bool
    {
        return self::lookup($key) !== null;
    }

    /**
     * Tutte le sezioni registrate, ordinate per `order` ascendente.
     */
    public static function all(): array
    {
        $sections = array_values(self::$registered);

        usort($sections, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $sections;
    }

    /**
     * Svuota il registry. Usato dai test in isolation; non chiamare
     * in production code path.
     */
    public static function reset(): void
    {
        self::$registered = [];
    }

    private static function sameRecord(array $a, array $b): bool
    {
        if ($a['title'] !== $b['title']) return false;
        if ($a['folder'] !== $b['folder']) return false;
        if ($a['icon'] !== $b['icon']) return false;
        if ($a['order'] !== $b['order']) return false;

        $authorityA = $a['authority'];
        $authorityB = $b['authority'];
        sort($authorityA);
        sort($authorityB);

        return $authorityA === $authorityB;
    }

    private static function dumpRecord(array $record): string
    {
        return sprintf(
            'title=%s, folder=%s, icon=%s, order=%d, authority=[%s]',
            $record['title'],
            $record['folder'],
            $record['icon'],
            $record['order'],
            implode(',', $record['authority'])
        );
    }

    private static function normalizeAuthority(array $authority): array
    {
        $authority = array_filter($authority, 'is_string');
        $authority = array_map(static fn (string $value): string => trim($value), $authority);
        $authority = array_filter($authority, static fn (string $value): bool => $value !== '');

        return array_values(array_unique($authority));
    }
}
