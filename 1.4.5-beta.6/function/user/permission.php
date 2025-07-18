<?php

    function permissions($PERMIT = null) {

        global $PERMITS;

        if ($PERMIT == null) {

            $RETURN = [];

            foreach ($PERMITS as $area => $array) {
                foreach ($array as $key => $value) { if ($key != 'links') { $RETURN[$key] = $value['name']; } }
            }

        } else {

            if (isset($PERMITS['backend'][$PERMIT])) {
                $AREA = 'backend';
            } elseif (isset($PERMITS['frontend'][$PERMIT])) {
                $AREA = 'frontend';
            }

            $ARRAY = $PERMITS[$AREA][$PERMIT];

            $home = $PERMITS[$AREA]['links']['home'] ?? '';
            $login = $PERMITS[$AREA]['links']['login'] ?? '';
            $signIn = $PERMITS[$AREA]['links']['sign-in'] ?? '';
            $passwordRecovery = $PERMITS[$AREA]['links']['password-recovery'] ?? '';
            $passwordRestore = $PERMITS[$AREA]['links']['password-restore'] ?? '';
            $passwordSet = $PERMITS[$AREA]['links']['password-set'] ?? '';

            $functionCreation = $PERMITS[$AREA]['function']['creation'] ?? '';
            $functionModify = $PERMITS[$AREA]['function']['modify'] ?? '';
            $functionInfo = $PERMITS[$AREA]['function']['info'] ?? '';

            $RETURN = (object) [];
            $RETURN->icon = $ARRAY['icon'] ?? '';
            $RETURN->name = $ARRAY['name'] ?? '';
            $RETURN->bg = $ARRAY['bg'] ?? '';
            $RETURN->tx = $ARRAY['tx'] ?? '';
            $RETURN->color = $ARRAY['color'] ?? '';
            $RETURN->creator = $ARRAY['creator'] ?? [];

            $RETURN->area = $AREA;

            $RETURN->home = $ARRAY['links']['home'] ?? $home;
            $RETURN->login = $ARRAY['links']['login'] ?? $login;
            $RETURN->signIn = $ARRAY['links']['sign-in'] ?? $signIn;
            $RETURN->passwordRecovery = $ARRAY['links']['password-recovery'] ?? $passwordRecovery;
            $RETURN->passwordRestore = $ARRAY['links']['password-restore'] ?? $passwordRestore;
            $RETURN->passwordSet = $ARRAY['links']['password-set'] ?? $passwordSet;

            $RETURN->functionCreation = $ARRAY['function']['creation'] ?? $functionCreation;
            $RETURN->functionModify = $ARRAY['function']['modify'] ?? $functionModify;
            $RETURN->functionInfo = $ARRAY['function']['info'] ?? $functionInfo;

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
                if ($key !== 'links' && $key !== 'function') {
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
            if ($RETURN->area != 'backend') { $RETURN = ""; }

        }

        return $RETURN;

    }

    function permissionsFrontend($PERMIT = null) {

        if ($PERMIT == null) {

            $RETURN = getPermissions('frontend');

        } else {
            
            $RETURN = permissions($PERMIT);
            if ($RETURN->area != 'frontend') { $RETURN = ""; }

        }

        return $RETURN;

    }

    function permissionsApi($PERMIT = null) {

        if ($PERMIT == null) {

            $RETURN = getPermissions('api');

        } else {
            
            $RETURN = permissions($PERMIT);
            if ($RETURN->area != 'api') { $RETURN = ""; }

        }

        return $RETURN;

    }