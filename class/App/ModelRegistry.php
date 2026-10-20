<?php

namespace Wonder\App;

use FilesystemIterator;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class ModelRegistry
{
    private static ?array $models = null;

    public static function reset(): void
    {
        self::$models = null;
    }

    /**
     * Mappa tabella => classe Model.
     *
     * Se più Model dichiarano la stessa `$table` vince il FQCN ultimo in
     * ordine alfabetico (vedi `ksort` in `discoveredModels()`), qualunque sia
     * la cartella di provenienza (pacchetto, moduli, `ROOT/app/Models`,
     * `ROOT/custom/class/Models`): il sito NON ha precedenza sul pacchetto.
     * Il conflitto non genera errori.
     *
     * @return array<string, class-string<Model>>
     */
    public static function all(): array
    {
        if (self::$models !== null) {
            return self::$models;
        }

        $models = [];

        foreach (self::discoveredModels() as $modelClass) {
            if (!class_exists($modelClass)) {
                throw new RuntimeException("Model non trovato: {$modelClass}");
            }

            if (!is_subclass_of($modelClass, Model::class)) {
                throw new RuntimeException("{$modelClass} deve estendere ".Model::class);
            }

            if ((new ReflectionClass($modelClass))->isAbstract()) {
                continue;
            }

            $table = trim((string) ($modelClass::$table ?? ''));

            if ($table === '') {
                throw new RuntimeException("Tabella non valida per il model {$modelClass}");
            }

            // Stessa tabella: sovrascrive, quindi resta il FQCN alfabeticamente ultimo.
            $models[$table] = $modelClass;
        }

        self::$models = $models;

        return self::$models;
    }

    public static function classes(): array
    {
        return array_values(self::all());
    }

    private static function discoveredModels(): array
    {
        $models = [];

        foreach (self::modelDirectories() as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (self::modelFiles($directory) as $file) {
                $modelClass = self::classFromFile($file);

                if ($modelClass === null) {
                    continue;
                }

                require_once $file;
                $models[$modelClass] = $modelClass;
            }
        }

        // Ordine per FQCN, non per cartella: decide la precedenza in all().
        ksort($models);

        return array_values($models);
    }

    private static function modelDirectories(): array
    {
        $packageRoot = dirname(__DIR__, 2);
        $directories = [
            $packageRoot.'/class/App/Models',
        ];

        foreach (\Wonder\App\Module\Registry::modelDirectories() as $directory) {
            if (is_string($directory) && trim($directory) !== '') {
                $directories[] = $directory;
            }
        }

        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        if (is_string($root) && $root !== '') {
            $directories[] = rtrim($root, '/').'/app/Models';
            $directories[] = rtrim($root, '/').'/custom/class/Models';
        }

        return $directories;
    }

    private static function modelFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $pathname = $file->getPathname();

            if (!str_ends_with($pathname, '.php')) {
                continue;
            }

            $files[] = $pathname;
        }

        sort($files);

        return $files;
    }

    private static function classFromFile(string $file): ?string
    {
        $contents = @file_get_contents($file);

        if (!is_string($contents) || $contents === '') {
            return null;
        }

        if (!preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch)) {
            return null;
        }

        if (!preg_match('/class\s+([a-zA-Z0-9_]+)/', $contents, $classMatch)) {
            return null;
        }

        return trim($namespaceMatch[1]).'\\'.trim($classMatch[1]);
    }
}
