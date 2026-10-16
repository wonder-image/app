<?php

namespace Wonder\App\Permission;

use RuntimeException;
use Wonder\App\LegacyGlobals;
use Wonder\App\Module\ConfigRepository;
use Wonder\App\Module\Registry;

/**
 * Mappa "area del backend → ruoli" (es. `log`, `avvisi`, `gestionale:catalogo`).
 *
 * Non va confusa con `Area` (backend / frontend / api): qui un'area è un gruppo
 * di pagine che condividono gli stessi permessi. Le Resource la leggono con
 * `PermissionSchema::access()` e `NavigationSchema::access()`.
 *
 * Una regola è una lista di ruoli (valida per tutte le azioni) o una mappa per
 * azione, dove `*` vale per le azioni non indicate:
 *
 *   ['administrator']
 *   ['view' => ['administrator'], 'create' => [], 'edit' => [], 'delete' => []]
 *   ['*' => ['administrator', 'sales'], 'delete' => ['administrator']]
 *
 * `admin` (Wonder Image) è sempre incluso e non si può togliere.
 *
 * Ordine di precedenza (vince l'ultimo, e sostituisce la regola intera):
 * 1. default del core (`CORE`)
 * 2. default dei moduli: chiave `permissions` di `config/module.php`, area `slug:chiave`
 * 3. override del sito per i moduli: `permissions` in `custom/config/modules/<slug>.php`
 * 4. aree e override del sito: `custom/config/access.php`, che restituisce
 *    `['area' => regola]` (anche `slug:chiave` per i moduli)
 * 5. `Access::set()` da codice
 *
 * Le regole si leggono alla prima richiesta, non al bootstrap: le route delle
 * Resource vengono registrate prima che i file di config del sito siano caricati.
 */
final class Access
{
    public const ACTIONS = ['view', 'create', 'edit', 'delete'];

    public const SUPER = 'admin';

    /** Aree del core. Lista vuota = solo admin. */
    public const CORE = [
        'log' => [],            // email inviate, accessi, consensi
        'diagnostica' => [],    // scheduler, errori, errori SQL
        'avvisi' => [],         // annunci e popup
    ];

    /** File del sito con aree e override, relativo a ROOT. */
    public const SITE_FILE = 'custom/config/access.php';

    /** Azione dell'area → azioni backend di `PermissionSchema`. */
    public const BACKEND_ACTIONS = [
        'view' => ['list', 'view'],
        'create' => ['create', 'store'],
        'edit' => ['edit', 'update'],
        'delete' => ['delete'],
    ];

    /** @var array<string, array<string, array<int, string>>> */
    private static array $defaults = [];

    /** @var array<string, array<string, array<int, string>>> */
    private static array $overrides = [];

    /** @var null|array<string, array<string, array<int, string>>> */
    private static ?array $resolved = null;

    private static bool $loadModules = true;

    public static function reset(bool $loadModules = true): void
    {
        self::$defaults = [];
        self::$overrides = [];
        self::$resolved = null;
        self::$loadModules = $loadModules;
    }

    /** Regola di default di un'area: un override (`set()`) la sostituisce. */
    public static function define(string $key, array $rule): void
    {
        self::$defaults[self::key($key)] = self::normalize($rule);
        self::$resolved = null;
    }

    public static function defineMany(array $rules, string $prefix = ''): void
    {
        foreach ($rules as $key => $rule) {
            self::define(self::prefixed((string) $key, $prefix), (array) $rule);
        }
    }

    /** Sostituisce per intero la regola di un'area (core o modulo). */
    public static function set(string $key, array $rule): void
    {
        self::$overrides[self::key($key)] = self::normalize($rule);
        self::$resolved = null;
    }

    public static function setMany(array $rules, string $prefix = ''): void
    {
        foreach ($rules as $key => $rule) {
            self::set(self::prefixed((string) $key, $prefix), (array) $rule);
        }
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[self::key($key)]);
    }

    /** @return array<string, array<int, string>> ruoli per azione */
    public static function rule(string $key): array
    {
        $key = self::key($key);
        $rules = self::all();

        if (!isset($rules[$key])) {
            throw new RuntimeException("Area permessi '{$key}' non definita.");
        }

        return $rules[$key];
    }

    /** @return array<int, string> */
    public static function roles(string $key, string $action = 'view'): array
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new RuntimeException("Azione '{$action}' non valida per l'area '{$key}'.");
        }

        return self::rule($key)[$action];
    }

    /** Ruoli che vedono almeno qualcosa in una o più aree (menu, sezioni). */
    public static function viewers(string ...$keys): array
    {
        $roles = [];

        foreach ($keys as $key) {
            $roles = array_merge($roles, self::roles($key, 'view'));
        }

        return array_values(array_unique($roles));
    }

    public static function allows(array $authorities, string $key, string $action = 'view'): bool
    {
        return array_intersect(self::roles($key, $action), $authorities) !== [];
    }

    /** @return array<string, array<int, string>> azioni backend di `PermissionSchema` */
    public static function backendActions(string $key): array
    {
        $actions = [];

        foreach (self::rule($key) as $action => $roles) {
            foreach (self::BACKEND_ACTIONS[$action] as $backendAction) {
                $actions[$backendAction] = $roles;
            }
        }

        return $actions;
    }

    /** @return array<string, array<string, array<int, string>>> */
    public static function all(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $rules = array_map(self::normalize(...), self::CORE);
        $rules = array_replace($rules, self::$defaults);

        if (self::$loadModules) {
            foreach (array_keys(Registry::enabled()) as $slug) {
                foreach ((array) (ConfigRepository::for($slug)['permissions'] ?? []) as $key => $rule) {
                    $rules[self::prefixed((string) $key, $slug)] = self::normalize((array) $rule);
                }
            }
        }

        if (self::$loadModules) {
            foreach (self::siteRules() as $key => $rule) {
                $rules[self::key((string) $key)] = self::normalize((array) $rule);
            }
        }

        self::$resolved = array_replace($rules, self::$overrides);
        ksort(self::$resolved);

        return self::$resolved;
    }

    /** @return array<string, array<int, string>> */
    public static function normalize(array $rule): array
    {
        $map = array_is_list($rule) ? ['*' => $rule] : $rule;

        foreach (array_keys($map) as $action) {
            if ($action !== '*' && !in_array($action, self::ACTIONS, true)) {
                throw new RuntimeException("Azione '{$action}' non valida: usa ".implode(', ', self::ACTIONS).' o *.');
            }
        }

        $normalized = [];

        foreach (self::ACTIONS as $action) {
            $roles = (array) ($map[$action] ?? $map['*'] ?? []);
            $roles = array_map(static fn (mixed $role): string => trim((string) $role), $roles);
            $roles = array_filter($roles, static fn (string $role): bool => $role !== '');

            $normalized[$action] = array_values(array_unique([self::SUPER, ...$roles]));
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private static function siteRules(): array
    {
        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        if (!is_string($root) || trim($root) === '') {
            return [];
        }

        $file = rtrim($root, '/').'/'.self::SITE_FILE;

        if (!is_file($file)) {
            return [];
        }

        $rules = require $file;

        if (!is_array($rules)) {
            throw new RuntimeException(self::SITE_FILE.' deve restituire un array area => regola.');
        }

        return $rules;
    }

    private static function prefixed(string $key, string $prefix): string
    {
        $prefix = trim($prefix);

        return $prefix === '' ? $key : $prefix.':'.$key;
    }

    private static function key(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            throw new RuntimeException('Il nome di un\'area permessi non può essere vuoto.');
        }

        return $key;
    }
}
