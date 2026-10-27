<?php

namespace Wonder\App\Visibility;

use Wonder\App\Resource;

/**
 * Condizione per id delle pagine e delle API generate di una Resource:
 * `getQuery('condition')` più la visibilità per riga dell'utente.
 */
final class ResourceRows
{
    /**
     * @param class-string<Resource> $resourceClass
     */
    public static function conditionForId(string $resourceClass, int $id, ?object $user): string|array
    {
        $condition = $resourceClass::getQuery('condition');

        if (is_array($condition)) {
            $condition['id'] = $id;
        } elseif (is_string($condition) && trim($condition) !== '') {
            $condition = "`id` = {$id} AND ({$condition})";
        } else {
            $condition = [ 'id' => $id ];
        }

        return RowVisibility::apply($resourceClass::modelTable(), $condition, $user);
    }

    /**
     * Riga per id o `null` se manca o l'utente non la vede.
     *
     * @param class-string<Resource> $resourceClass
     */
    public static function find(string $resourceClass, int $id, ?object $user, array|string $fields = '*'): ?array
    {
        $modelClass = $resourceClass::modelClass();
        $row = $modelClass::find(self::conditionForId($resourceClass, $id, $user), 1, null, null, $fields);

        return is_array($row) && $row !== [] ? $row : null;
    }
}
