<?php

namespace Wonder\OAuth;

/** Il parametro `scope` (RFC 6749 §3.3): elenco separato da spazi, senza ordine né doppioni. */
final class Scope
{
    /** @return list<string> */
    public static function parse(string $scope): array
    {
        $list = array_values(array_unique(preg_split('/ +/', trim($scope), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        sort($list);
        return $list;
    }

    /** @param list<string> $scopes */
    public static function join(array $scopes): string
    {
        return implode(' ', self::parse(implode(' ', $scopes)));
    }

    /**
     * Gli scope di un grant per la sua risorsa. Un grant senza scope (nato
     * prima degli scope o su una risorsa che non ne dichiara) vale tutti quelli della risorsa.
     *
     * @return list<string>
     */
    public static function granted(string $grantScope, array $resource): array
    {
        $scopes = self::parse($grantScope);
        return $scopes !== [] ? $scopes : self::parse(implode(' ', array_keys($resource['scopes'] ?? [])));
    }
}
