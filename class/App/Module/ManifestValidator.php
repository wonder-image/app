<?php

namespace Wonder\App\Module;

use RuntimeException;
use Wonder\App\Module\Contracts\ModuleInterface;

final class ManifestValidator
{
    public static function assertValid(Manifest $manifest): void
    {
        $errors = self::errors($manifest);

        if ($errors === []) {
            return;
        }

        throw new RuntimeException(
            'Manifest modulo non valido per '.$manifest->manifestPath().': '.implode(' | ', $errors)
        );
    }

    public static function errors(Manifest $manifest): array
    {
        $errors = [];

        foreach (['name', 'slug', 'version', 'description', 'namespace', 'entrypoint'] as $field) {
            if (trim((string) $manifest->get($field, '')) === '') {
                $errors[] = "Campo obbligatorio mancante: {$field}";
            }
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $manifest->slug())) {
            $errors[] = 'Slug non valido: usare kebab-case lowercase';
        }

        if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $manifest->version())) {
            $errors[] = 'Versione non valida: usare semver';
        }

        if (!preg_match('/^Wonder\\\\Plugin\\\\[A-Z][A-Za-z0-9]*\\\\$/', $manifest->namespace())) {
            $errors[] = 'Namespace non valido: usare Wonder\\Plugin\\<Modulo>\\';
        }

        $compatibility = $manifest->frameworkCompatibility();

        if (!isset($compatibility['wonder-app']) || !is_string($compatibility['wonder-app']) || trim($compatibility['wonder-app']) === '') {
            $errors[] = 'frameworkCompatibility.wonder-app obbligatorio';
        }

        if (!isset($compatibility['php']) || !is_string($compatibility['php']) || trim($compatibility['php']) === '') {
            $errors[] = 'frameworkCompatibility.php obbligatorio';
        }

        $entrypoint = $manifest->entrypoint();
        $legacy = (bool) $manifest->get('legacy', false);

        if ($entrypoint !== '') {
            if (!class_exists($entrypoint)) {
                $errors[] = 'Entrypoint non autoloadabile: '.$entrypoint;
            } elseif (!$legacy && !is_subclass_of($entrypoint, ModuleInterface::class)) {
                $errors[] = $entrypoint.' deve implementare '.ModuleInterface::class;
            }
        }

        foreach ([
            'src' => $manifest->srcPath(),
            'handlers' => $manifest->handlersPath(),
            'views' => $manifest->viewsPath(),
            'assets' => $manifest->assetsPath(),
            'lang' => $manifest->langPath(),
            'models' => $manifest->modelsPath(),
            'resources' => $manifest->resourcesPath(),
            // Path AI: presenti solo se il modulo ha dichiarato la sezione
            // `ai` nel `module.json`. Stesso check di isPathInsideRoot per
            // evitare directory traversal verso vendor/ o all'esterno.
            'ai.agents' => $manifest->aiAgentsPath(),
            'ai.prompts' => $manifest->aiPromptsPath(),
            'ai.tools' => $manifest->aiToolsPath(),
        ] as $name => $path) {
            if ($path === null) {
                continue;
            }

            if (!self::isPathInsideRoot($path, $manifest->root())) {
                $errors[] = "Path {$name} fuori dal root modulo";
            }
        }

        foreach (['frontend', 'backend', 'api'] as $area) {
            $path = $manifest->routeFile($area);

            if ($path === null) {
                continue;
            }

            if (!self::isPathInsideRoot($path, $manifest->root())) {
                $errors[] = "Route {$area} fuori dal root modulo";
                continue;
            }

            if (!is_file($path)) {
                $errors[] = "File route {$area} mancante";
            }
        }

        $permissionsFile = $manifest->permissionsFile();

        if ($permissionsFile !== null && !self::isPathInsideRoot($permissionsFile, $manifest->root())) {
            $errors[] = 'File permissions fuori dal root modulo';
        }

        return $errors;
    }

    private static function isPathInsideRoot(string $path, string $root): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = str_replace('\\', '/', $path);

        return $path === $root || str_starts_with($path, $root.'/');
    }
}
