<?php

    // Chiavi riservate nella configurazione permessi (non sono permessi utente).
    function permissionReservedKeys(): array
    {

        return [ 'links', 'function', 'verification' ];

    }

    // Verifica se una voce dell'array è un permesso reale.
    function permissionEntryIsDefinition(string $key, $value): bool
    {

        if (in_array($key, permissionReservedKeys(), true)) {
            return false;
        }

        if (!is_array($value)) {
            return false;
        }

        return isset($value['name']);

    }

    // Estrae le regole di verifica dichiarate nel permesso.
    function userPermissionVerificationRules($PERMISSION): array
    {

        if (!is_object($PERMISSION) || !isset($PERMISSION->verification) || !is_array($PERMISSION->verification)) {
            return [];
        }

        return $PERMISSION->verification;

    }

    // Indica se una singola regola di verifica è obbligatoria.
    function userVerificationRuleRequired(array $rule): bool
    {

        $required = $rule['required'] ?? false;

        return $required === true || $required === 1 || $required === '1' || $required === 'true';

    }

    // Restituisce solo le verifiche obbligatorie di un permesso.
    function userPermissionRequiredVerifications(array $rules): array
    {

        $required = [];

        foreach ($rules as $key => $rule) {
            if ($key !== '*' && is_array($rule) && userVerificationRuleRequired($rule)) {
                $required[$key] = $rule;
            }
        }

        return $required;

    }

    // Ritorna rapidamente un link permesso (es. login, sign-in) o tutto il blocco links.
    function getPermissionLink(?string $area, ?string $permission = null, ?string $link = null)
    {

        global $PERMITS;

        $area = strtolower(trim((string) $area));
        $permission = $permission !== null ? trim($permission) : null;
        $link = $link !== null ? trim($link) : null;

        if ($permission !== null && $permission !== '') {
            $permissionInfo = permissions($permission);
            if (is_object($permissionInfo) && isset($permissionInfo->area) && $permissionInfo->area !== '') {
                $area = (string) $permissionInfo->area;
            }
        }

        if ($area === '' || !isset($PERMITS[$area]) || !is_array($PERMITS[$area])) {
            return $link === null ? [] : '';
        }

        $areaLinks = isset($PERMITS[$area]['links']) && is_array($PERMITS[$area]['links']) ? $PERMITS[$area]['links'] : [];
        $permissionLinks = [];

        if ($permission !== null && $permission !== '' && isset($PERMITS[$area][$permission]) && is_array($PERMITS[$area][$permission])) {
            $permissionLinks = isset($PERMITS[$area][$permission]['links']) && is_array($PERMITS[$area][$permission]['links'])
                ? $PERMITS[$area][$permission]['links']
                : [];
        }

        $links = array_merge($areaLinks, $permissionLinks);

        if ($link === null || $link === '') {
            return $links;
        }

        return $links[$link] ?? '';

    }

    function permissions($PERMIT = null) {

        global $PERMITS;

        if ($PERMIT == null) {

            $RETURN = [];

            foreach ($PERMITS as $area => $array) {
                foreach ($array as $key => $value) {
                    if (permissionEntryIsDefinition($key, $value)) {
                        $RETURN[$key] = $value['name'];
                    }
                }
            }

        } else {

            $AREA = null;

            if (isset($PERMITS['backend'][$PERMIT])) {
                $AREA = 'backend';
            } elseif (isset($PERMITS['frontend'][$PERMIT])) {
                $AREA = 'frontend';
            } elseif (isset($PERMITS['api'][$PERMIT])) {
                $AREA = 'api';
            }

            if ($AREA === null || !isset($PERMITS[$AREA][$PERMIT]) || !is_array($PERMITS[$AREA][$PERMIT])) {
                return (object) [];
            }

            $ARRAY = $PERMITS[$AREA][$PERMIT];
            $AREA_LINKS = isset($PERMITS[$AREA]['links']) && is_array($PERMITS[$AREA]['links']) ? $PERMITS[$AREA]['links'] : [];
            $PERMIT_LINKS = isset($ARRAY['links']) && is_array($ARRAY['links']) ? $ARRAY['links'] : [];
            $LINKS = array_merge($AREA_LINKS, $PERMIT_LINKS);

            $AREA_FUNCTION = isset($PERMITS[$AREA]['function']) && is_array($PERMITS[$AREA]['function']) ? $PERMITS[$AREA]['function'] : [];
            $PERMIT_FUNCTION = isset($ARRAY['function']) && is_array($ARRAY['function']) ? $ARRAY['function'] : [];
            $FUNCTION = array_merge($AREA_FUNCTION, $PERMIT_FUNCTION);

            $AREA_VERIFICATION = isset($PERMITS[$AREA]['verification']) && is_array($PERMITS[$AREA]['verification']) ? $PERMITS[$AREA]['verification'] : [];
            $PERMIT_VERIFICATION = isset($ARRAY['verification']) && is_array($ARRAY['verification']) ? $ARRAY['verification'] : [];
            $VERIFICATION = array_replace_recursive($AREA_VERIFICATION, $PERMIT_VERIFICATION);

            $RETURN = (object) [];
            $RETURN->icon = $ARRAY['icon'] ?? '';
            $RETURN->name = $ARRAY['name'] ?? '';
            $RETURN->bg = $ARRAY['bg'] ?? '';
            $RETURN->tx = $ARRAY['tx'] ?? '';
            $RETURN->color = $ARRAY['color'] ?? '';
            $RETURN->creator = $ARRAY['creator'] ?? [];

            $RETURN->area = $AREA;
            $RETURN->links = $LINKS;
            $RETURN->verification = $VERIFICATION;

            $RETURN->home = $LINKS['home'] ?? '';
            $RETURN->login = $LINKS['login'] ?? '';
            $RETURN->signIn = $LINKS['sign-in'] ?? '';
            $RETURN->passwordRecovery = $LINKS['password-recovery'] ?? '';
            $RETURN->passwordRestore = $LINKS['password-restore'] ?? '';
            $RETURN->passwordSet = $LINKS['password-set'] ?? '';

            $RETURN->functionCreation = $FUNCTION['creation'] ?? '';
            $RETURN->functionModify = $FUNCTION['modify'] ?? '';
            $RETURN->functionInfo = $FUNCTION['info'] ?? '';
            $RETURN->functionValidate = $FUNCTION['validate'] ?? '';

            $RETURN->badge = "<span class='badge $RETURN->bg $RETURN->tx'>".strtoupper($RETURN->name)."</span>";
            $RETURN->badgeIcon = "<span class='badge $RETURN->bg $RETURN->tx'>$RETURN->icon</span>";
            $RETURN->automaticResize = "<span class='phone-none badge $RETURN->bg $RETURN->tx'>".strtoupper($RETURN->name)."</span><span class='pc-none badge $RETURN->bg $RETURN->tx'>$RETURN->icon</span>";

        }

        return $RETURN;

    }

    function getPermissions($AREA = null) {

        global $PERMITS;

        $RETURN = [];

        $areas = $AREA ? [$AREA] : array_keys($PERMITS);

        foreach ($areas as $area) {
            foreach ($PERMITS[$area] as $key => $value) {
                if (permissionEntryIsDefinition($key, $value)) {
                    $RETURN[$key] = $value['name'];
                }
            }
        }
                    
        return $RETURN;

    }

    function permissionsBackend($PERMIT = null) {

        if ($PERMIT == null) {

            $RETURN = getPermissions('backend');

        } else {
            
            $RETURN = permissions($PERMIT);
            if (!is_object($RETURN) || !isset($RETURN->area) || $RETURN->area != 'backend') { $RETURN = ""; }

        }

        return $RETURN;

    }

    function permissionsFrontend($PERMIT = null) {

        if ($PERMIT == null) {

            $RETURN = getPermissions('frontend');

        } else {
            
            $RETURN = permissions($PERMIT);
            if (!is_object($RETURN) || !isset($RETURN->area) || $RETURN->area != 'frontend') { $RETURN = ""; }

        }

        return $RETURN;

    }

    function permissionsApi($PERMIT = null) {

        if ($PERMIT == null) {

            $RETURN = getPermissions('api');

        } else {
            
            $RETURN = permissions($PERMIT);
            if (!is_object($RETURN) || !isset($RETURN->area) || $RETURN->area != 'api') { $RETURN = ""; }

        }

        return $RETURN;

    }
