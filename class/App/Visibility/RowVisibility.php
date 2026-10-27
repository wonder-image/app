<?php

namespace Wonder\App\Visibility;

use LogicException;
use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;
use Wonder\Sql\Query;

/**
 * Visibilità per riga: quali righe di una tabella può leggere un utente.
 *
 * Una regola riceve l'utente e restituisce una condizione (array
 * `colonna => valore` o stringa SQL scritta dal codice), oppure `null` per
 * nessuna restrizione. Si dichiara da una Resource (`rowVisibility()`) o
 * dall'area con `rule()`; più regole della stessa tabella vanno in AND.
 * I ruoli "vede tutto" (default `admin`, `administrator`) le saltano.
 *
 * Senza regola applicabile ogni metodo restituisce la condizione ricevuta
 * identica: una tabella senza regola si comporta come prima.
 * Utente `null` (CLI, scheduler) = nessuna restrizione.
 *
 * Vedi `docs/app/concetti/risorse/visibilita-per-riga.md`.
 */
final class RowVisibility
{
    public const SEE_ALL = [ 'admin', 'administrator' ];

    /** @var array<string, list<array{rule: callable, seeAll: list<string>}>> */
    private static array $rules = [];

    /** @var list<object|null> */
    private static array $users = [];

    /** @var list<class-string<Resource>>|null */
    private static ?array $resources = null;

    /** @var array{source: array, map: array<string, list<class-string<Resource>>>}|null */
    private static ?array $resourceMap = null;

    private static ?Query $query = null;

    /**
     * Regola d'area per `$table`, senza toccare la Resource.
     *
     * @param callable(object): (string|array|null) $rule
     * @param list<string>|null $seeAll ruoli che vedono tutto (default SEE_ALL)
     */
    public static function rule(string $table, callable $rule, ?array $seeAll = null): void
    {
        self::$rules[trim($table)][] = [
            'rule' => $rule,
            'seeAll' => array_values(array_map('strval', $seeAll ?? self::SEE_ALL)),
        ];
    }

    public static function reset(): void
    {
        self::$rules = [];
        self::$users = [];
        self::$resources = null;
        self::$resourceMap = null;
        self::$query = null;
    }

    /** Solo per i test: Resource da considerare al posto del registro. */
    public static function useResources(?array $resources): void
    {
        self::$resources = $resources === null ? null : array_values($resources);
        self::$resourceMap = null;
    }

    /** Solo per i test: Query senza connessione per il binding dei valori. */
    public static function useQuery(?Query $query): void
    {
        self::$query = $query;
    }

    /** Condizione che non trova nessuna riga. */
    public static function none(): string
    {
        return '1 = 0';
    }

    /** true se esiste almeno una regola dichiarata per la tabella (per qualunque utente). */
    public static function hasRule(string $table): bool
    {
        if (!empty(self::$rules[trim($table)])) {
            return true;
        }

        foreach (self::resourcesFor($table) as $resourceClass) {
            if (self::declaresHook($resourceClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Condizione della regola per l'utente, o `null` se non c'è restrizione.
     */
    public static function conditionFor(string $table, ?object $user): string|array|null
    {
        if ($user === null) {
            return null;
        }

        $table = trim($table);
        $authorities = self::authorities($user);
        $parts = [];

        foreach (self::$rules[$table] ?? [] as $entry) {
            if (array_intersect($authorities, $entry['seeAll']) !== []) {
                continue;
            }

            $parts[] = ($entry['rule'])($user);
        }

        foreach (self::resourcesFor($table) as $resourceClass) {
            $seeAll = array_values(array_map('strval', $resourceClass::rowVisibilitySeeAll()));

            if (array_intersect($authorities, $seeAll) !== []) {
                continue;
            }

            $parts[] = $resourceClass::rowVisibility($user);
        }

        $parts = array_values(array_filter($parts, static fn ($part): bool => $part !== null));

        foreach ($parts as $part) {
            self::assertRule($part);
        }

        if ($parts === []) {
            return null;
        }

        if (count($parts) === 1) {
            return self::isPlainArray($parts[0]) || is_string($parts[0]) ? self::normalize($parts[0]) : self::toSql($parts[0]);
        }

        return implode(' AND ', array_map(static fn ($part): string => '('.self::toSql($part).')', $parts));
    }

    /**
     * Aggiunge la regola a `$condition`. Senza regola applicabile la
     * restituisce identica (stesso tipo e valore).
     */
    public static function apply(string $table, string|array|null $condition, ?object $user): string|array|null
    {
        $rule = self::conditionFor($table, $user);

        if ($rule === null) {
            return $condition;
        }

        if ($condition === null || $condition === [] || (is_string($condition) && trim($condition) === '')) {
            return $rule;
        }

        if (is_array($condition) && is_array($rule) && self::isPlainArray($condition)
            && array_intersect_key(self::keys($condition), self::keys($rule)) === []) {
            return $condition + $rule;
        }

        return '('.self::toSql($condition).') AND ('.self::toSql($rule).')';
    }

    /** Come `apply()` ma sempre stringa SQL (vuota se non c'è né condizione né regola). */
    public static function applySql(string $table, string|array|null $condition, ?object $user): string
    {
        $applied = self::apply($table, $condition, $user);

        return $applied === null ? '' : (is_array($applied) ? self::toSql($applied) : $applied);
    }

    /** true se l'utente può vedere la riga `$id`. Senza regola nessuna query. */
    public static function visible(string $table, int|string $id, ?object $user): bool
    {
        $rule = self::conditionFor($table, $user);

        if ($rule === null) {
            return true;
        }

        $query = self::query();
        $sql = $query->bind('`id` = ?', [ (int) $id ]).' AND ('.self::toSql($rule).')';

        return $query->Count($table, $sql, 'id') > 0;
    }

    /**
     * Utente delle letture: quello di `as()`, altrimenti `$USER` della
     * richiesta, altrimenti `null` (nessuna restrizione).
     */
    public static function currentUser(): ?object
    {
        if (self::$users !== []) {
            return self::$users[array_key_last(self::$users)];
        }

        $user = LegacyGlobals::get('USER');

        return is_object($user) ? $user : null;
    }

    /** Utente della sessione o, senza sessione, l'anonimo. */
    public static function sessionUser(): object
    {
        $id = $_SESSION['user_id'] ?? null;

        if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0 && function_exists('infoUser')) {
            $user = infoUser((int) $id);

            if (is_object($user) && !empty($user->exists)) {
                return $user;
            }
        }

        return self::anonymous();
    }

    public static function anonymous(): object
    {
        return (object) [ 'id' => 0, 'authority' => [] ];
    }

    /**
     * Esegue `$callback` come `$user` (anche `null` = nessuna restrizione).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function as(?object $user, callable $callback): mixed
    {
        self::$users[] = $user;

        try {
            return $callback();
        } finally {
            array_pop(self::$users);
        }
    }

    /** Letture legate all'utente corrente (MCP, widget, opzioni). */
    public static function current(): RowVisibilityScope
    {
        return new RowVisibilityScope(self::currentUser());
    }

    public static function for(?object $user): RowVisibilityScope
    {
        return new RowVisibilityScope($user);
    }

    /** Condizione array → SQL con valori bindati; stringa invariata. */
    public static function toSql(string|array $condition): string
    {
        if (is_string($condition)) {
            return $condition;
        }

        if ($condition === []) {
            return '1 = 1';
        }

        $parts = [];
        $params = [];

        foreach ($condition as $column => $value) {
            $identifier = Query::escapeIdentifier(trim((string) $column, "` \t\n\r\0\x0B"));

            if ($value === null) {
                $parts[] = "{$identifier} IS NULL";
            } elseif (is_array($value)) {
                if ($value === []) {
                    $parts[] = self::none();
                    continue;
                }

                $parts[] = "{$identifier} IN (".implode(', ', array_fill(0, count($value), '?')).')';
                array_push($params, ...array_values($value));
            } else {
                $parts[] = "{$identifier} = ?";
                $params[] = $value;
            }
        }

        return $params === [] ? implode(' AND ', $parts) : self::query()->bind(implode(' AND ', $parts), $params);
    }

    /** @return list<string> */
    public static function authorities(object $user): array
    {
        $authority = $user->authority ?? [];

        if (is_string($authority)) {
            $decoded = json_decode($authority, true);
            $authority = is_array($decoded) ? $decoded : ($authority === '' ? [] : [ $authority ]);
        }

        return array_values(array_map('strval', (array) $authority));
    }

    /** @return list<class-string<Resource>> */
    private static function resourcesFor(string $table): array
    {
        $source = self::$resources ?? ResourceRegistry::classes();

        if (self::$resourceMap === null || self::$resourceMap['source'] !== $source) {
            $map = [];

            foreach ($source as $resourceClass) {
                try {
                    $map[$resourceClass::modelTable()][] = $resourceClass;
                } catch (Throwable) {
                    // Resource senza Model valido: non legge righe.
                }
            }

            self::$resourceMap = [ 'source' => $source, 'map' => $map ];
        }

        return self::$resourceMap['map'][trim($table)] ?? [];
    }

    private static function declaresHook(string $resourceClass): bool
    {
        return (new \ReflectionMethod($resourceClass, 'rowVisibility'))->getDeclaringClass()->getName() !== Resource::class;
    }

    private static function assertRule(mixed $rule): void
    {
        if (!is_string($rule) && !is_array($rule)) {
            throw new LogicException('RowVisibility: una regola restituisce array, stringa o null.');
        }

        $text = is_string($rule) ? $rule : implode(' ', array_map('strval', array_keys($rule)));

        // Model::find() salta il soft delete se la condizione nomina `deleted`.
        if (preg_match('/\bdeleted\b/i', $text) === 1) {
            throw new LogicException('RowVisibility: una regola non può nominare la colonna deleted.');
        }

        if (is_string($rule) && trim($rule) === '') {
            throw new LogicException('RowVisibility: regola stringa vuota (usa null per nessuna restrizione).');
        }
    }

    /** Array con soli valori scalari: resta array per Model::find(). */
    private static function isPlainArray(mixed $condition): bool
    {
        if (!is_array($condition) || $condition === []) {
            return false;
        }

        foreach ($condition as $key => $value) {
            if (is_int($key) || !is_scalar($value)) {
                return false;
            }
        }

        return true;
    }

    private static function normalize(string|array $rule): string|array
    {
        return is_string($rule) ? trim($rule) : $rule;
    }

    /** @return array<string, true> */
    private static function keys(array $condition): array
    {
        $keys = [];

        foreach (array_keys($condition) as $key) {
            $keys[trim((string) $key, "` \t\n\r\0\x0B")] = true;
        }

        return $keys;
    }

    private static function query(): Query
    {
        return self::$query ??= new Query();
    }
}
