<?php

    if (!function_exists('wonder_resolve_root')) {
        function wonder_resolve_root(): string
        {
            $candidates = [];

            if (isset($GLOBALS['ROOT']) && is_string($GLOBALS['ROOT']) && $GLOBALS['ROOT'] !== '') {
                $candidates[] = $GLOBALS['ROOT'];
            }

            if (isset($ROOT) && is_string($ROOT) && $ROOT !== '') {
                $candidates[] = $ROOT;
            }

            $cwd = getcwd();
            if (is_string($cwd) && $cwd !== '') {
                $candidates[] = $cwd;
            }

            $candidates[] = __DIR__;
            $candidates[] = dirname(__DIR__, 3);

            foreach ($candidates as $candidate) {
                $candidate = rtrim((string) $candidate, '/');

                if ($candidate === '') {
                    continue;
                }

                if (file_exists($candidate.'/vendor/autoload.php')) {
                    return $candidate;
                }
            }

            throw new RuntimeException('Impossibile risolvere ROOT per wonder-image.php');
        }
    }

    $ROOT = wonder_resolve_root();
    $GLOBALS['ROOT'] = $ROOT;

    $APP_VERSION = "2.0.0";
    $ROOT_APP = __DIR__."/app";
    $ROOT_RESOURCES = __DIR__."/resources";

    require_once $ROOT."/vendor/autoload.php";

    $legacyRuntime = \Wonder\App\LegacyGlobals::scope();
    if (is_array($legacyRuntime) && $legacyRuntime !== []) {
        extract($legacyRuntime, EXTR_SKIP);
    }

    require_once $ROOT_APP."/function/function.php";
    
    require_once $ROOT_APP."/config/config.php";
    \Wonder\App\LegacyGlobals::capture(get_defined_vars());
    
    require_once $ROOT_APP."/service/service.php";

    require_once $ROOT_APP."/middleware/middleware.php";

    if (isset($BACKEND) && $BACKEND) { require_once $ROOT_APP."/utility/backend/set-up.php"; }

    if (isset($FRONTEND) && $FRONTEND) { require_once $ROOT_APP."/utility/frontend/set-up.php"; }

    \Wonder\App\LegacyGlobals::capture(get_defined_vars());
