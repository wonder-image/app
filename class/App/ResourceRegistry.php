<?php

namespace Wonder\App;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class ResourceRegistry
{
    private static ?array $resources = null;

    public static function reset(): void
    {
        self::$resources = null;
    }

    public static function all(): array
    {
        if (self::$resources !== null) {
            return self::$resources;
        }

        $resources = [];
        $priorities = [];

        foreach (self::resourceCandidates() as $candidate) {
            $resourceClass = $candidate['class'];
            $priority = (int) ($candidate['priority'] ?? 0);
            $source = (string) ($candidate['source'] ?? 'resource');

            if (!is_string($resourceClass) || trim($resourceClass) === '') {
                continue;
            }

            if (!class_exists($resourceClass)) {
                throw new RuntimeException("Resource non trovata: {$resourceClass} ({$source})");
            }

            if (!is_subclass_of($resourceClass, Resource::class)) {
                throw new RuntimeException("{$resourceClass} deve estendere ".Resource::class." ({$source})");
            }

            $slug = $resourceClass::slug();

            if ($slug === '') {
                throw new RuntimeException("Slug vuoto per la resource {$resourceClass} ({$source})");
            }

            if (isset($resources[$slug])) {
                $currentPriority = (int) ($priorities[$slug] ?? 0);

                if ($priority === $currentPriority && $resources[$slug] !== $resourceClass) {
                    throw new RuntimeException("Slug duplicato nel ResourceRegistry: {$slug}");
                }

                if ($priority < $currentPriority) {
                    continue;
                }
            }

            $resources[$slug] = $resourceClass;
            $priorities[$slug] = $priority;
        }

        self::$resources = $resources;

        return self::$resources;
    }

    public static function classes(): array
    {
        return array_values(self::all());
    }

    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $slug): bool
    {
        return array_key_exists(trim($slug), self::all());
    }

    public static function resolve(string $slug): string
    {
        $slug = trim($slug);

        if (!self::has($slug)) {
            throw new RuntimeException("Resource non registrata: {$slug}");
        }

        return self::all()[$slug];
    }

    private static function resourceCandidates(): array
    {
        $candidates = [];
        $seen = [];

        foreach (self::discoveredResources() as $candidate) {
            $class = (string) ($candidate['class'] ?? '');

            if ($class === '' || isset($seen[$class])) {
                continue;
            }

            $seen[$class] = true;
            $candidates[] = $candidate;
        }

        foreach (self::configuredResources() as $candidate) {
            $class = (string) ($candidate['class'] ?? '');

            if ($class === '') {
                continue;
            }

            if (isset($seen[$class])) {
                foreach ($candidates as $index => $existing) {
                    if (($existing['class'] ?? '') !== $class) {
                        continue;
                    }

                    $candidates[$index] = $candidate;
                    continue 2;
                }
            }

            $seen[$class] = true;
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    private static function discoveredResources(): array
    {
        $resources = [];

        foreach (self::resourceDirectories() as $directory) {
            $path = (string) ($directory['path'] ?? '');
            $priority = (int) ($directory['priority'] ?? 0);
            $source = (string) ($directory['source'] ?? $path);

            if ($path === '' || !is_dir($path)) {
                continue;
            }

            foreach (self::resourceFiles($path) as $file) {
                $resourceClass = self::classFromFile($file);

                if ($resourceClass === null) {
                    continue;
                }

                require_once $file;

                $resources[] = [
                    'class' => $resourceClass,
                    'priority' => $priority,
                    'source' => $source.':'.$file,
                ];
            }
        }

        return $resources;
    }

    private static function configuredResources(): array
    {
        $resources = [];

        foreach (self::configFiles() as $file) {
            if (!is_string($file) || $file === '' || !file_exists($file)) {
                continue;
            }

            $configured = require $file;

            if (!is_array($configured)) {
                continue;
            }

            foreach ($configured as $resourceClass) {
                if (!is_string($resourceClass) || trim($resourceClass) === '') {
                    continue;
                }

                $resources[] = [
                    'class' => $resourceClass,
                    'priority' => str_contains($file, '/custom/') ? 40 : 20,
                    'source' => $file,
                ];
            }
        }

        return $resources;
    }

    private static function resourceDirectories(): array
    {
        $packageRoot = dirname(__DIR__, 2);
        $directories = [
            [
                'path' => $packageRoot.'/class/App/Resources',
                'priority' => 10,
                'source' => 'package',
            ],
        ];

        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        if (is_string($root) && $root !== '') {
            $directories[] = [
                'path' => rtrim($root, '/').'/custom/class/Resources',
                'priority' => 30,
                'source' => 'custom',
            ];
        }

        return $directories;
    }

    private static function resourceFiles(string $directory): array
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

            if (!str_ends_with($pathname, 'Resource.php')) {
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

        $tokens = token_get_all($contents);
        $namespace = '';
        $className = '';
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = self::parseNamespace($tokens, $index + 1);
                continue;
            }

            if ($token[0] === T_CLASS) {
                $className = self::parseClassName($tokens, $index + 1);
                break;
            }
        }

        if ($className === '') {
            return null;
        }

        return $namespace !== '' ? $namespace.'\\'.$className : $className;
    }

    private static function parseNamespace(array $tokens, int $index): string
    {
        $parts = [];
        $count = count($tokens);

        for (; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_string($token) && ($token === ';' || $token === '{')) {
                break;
            }

            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $parts[] = $token[1];
            }
        }

        return trim(implode('', $parts), '\\');
    }

    private static function parseClassName(array $tokens, int $index): string
    {
        $count = count($tokens);

        for (; $index < $count; $index++) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_STRING) {
                return trim($token[1]);
            }
        }

        return '';
    }

    private static function configFiles(): array
    {
        $packageRoot = dirname(__DIR__, 2);
        $files = [
            $packageRoot.'/app/config/resource/resources.php',
        ];

        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        if (is_string($root) && $root !== '') {
            $files[] = rtrim($root, '/').'/custom/config/resource/resources.php';
        }

        return $files;
    }
}
