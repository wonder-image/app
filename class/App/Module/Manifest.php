<?php

namespace Wonder\App\Module;

final class Manifest
{
    public function __construct(
        private readonly string $root,
        private readonly string $manifestPath,
        private readonly array $data,
        private readonly string $source,
        private readonly int $priority,
        private readonly ?string $composerPackage = null,
    ) {
    }

    public static function fromFile(
        string $manifestPath,
        string $source,
        int $priority = 0,
        ?string $composerPackage = null,
    ): self {
        $json = @file_get_contents($manifestPath);
        $data = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($data)) {
            $message = json_last_error() === JSON_ERROR_NONE
                ? 'Manifest modulo non valido'
                : 'Manifest modulo non valido: '.json_last_error_msg();

            throw new \RuntimeException($message.' ('.$manifestPath.')');
        }

        return self::fromArray(
            dirname($manifestPath),
            $manifestPath,
            $data,
            $source,
            $priority,
            $composerPackage
        );
    }

    public static function fromArray(
        string $root,
        string $manifestPath,
        array $data,
        string $source,
        int $priority = 0,
        ?string $composerPackage = null,
    ): self {
        return new self($root, $manifestPath, $data, $source, $priority, $composerPackage);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function manifestPath(): string
    {
        return $this->manifestPath;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function composerPackage(): ?string
    {
        return $this->composerPackage;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $current = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public function name(): string
    {
        return trim((string) $this->get('name', ''));
    }

    public function slug(): string
    {
        return trim((string) $this->get('slug', ''));
    }

    public function version(): string
    {
        return trim((string) $this->get('version', ''));
    }

    public function description(): string
    {
        return trim((string) $this->get('description', ''));
    }

    public function namespace(): string
    {
        return trim((string) $this->get('namespace', ''));
    }

    public function entrypoint(): string
    {
        return trim((string) $this->get('entrypoint', ''));
    }

    public function frameworkCompatibility(): array
    {
        $compatibility = $this->get('frameworkCompatibility', []);

        return is_array($compatibility) ? $compatibility : [];
    }

    public function dependencies(): array
    {
        $dependencies = $this->get('dependencies', []);

        return is_array($dependencies) ? $dependencies : [];
    }

    public function dependencySlugs(): array
    {
        $modules = $this->dependencies()['modules'] ?? [];

        if (!is_array($modules)) {
            return [];
        }

        $slugs = [];

        foreach ($modules as $key => $value) {
            if (is_string($key) && trim($key) !== '') {
                $slugs[] = trim($key);
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $slugs[] = trim($value);
            }
        }

        return array_values(array_unique($slugs));
    }

    public function path(string $key, ?string $default = null): ?string
    {
        $path = $this->get('paths.'.$key, $default);

        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        return trim($path);
    }

    public function srcPath(): ?string
    {
        return $this->resolvePath($this->path('src', 'src'));
    }

    public function handlersPath(): ?string
    {
        return $this->resolvePath($this->path('handlers', 'handlers'));
    }

    public function viewsPath(): ?string
    {
        return $this->resolvePath($this->path('views', 'views'));
    }

    public function assetsPath(): ?string
    {
        return $this->resolvePath($this->path('assets', 'resources/assets'));
    }

    public function langPath(): ?string
    {
        return $this->resolvePath($this->path('lang', 'lang'));
    }

    public function testsPath(): ?string
    {
        return $this->resolvePath($this->path('tests', 'tests'));
    }

    public function modelsPath(): ?string
    {
        $path = $this->get('database.models');

        if (is_string($path) && trim($path) !== '') {
            return $this->resolvePath(trim($path));
        }

        $src = $this->srcPath();

        return $src !== null ? $src.'/Models' : null;
    }

    public function resourcesPath(): ?string
    {
        $path = $this->get('resources.classes');

        if (is_string($path) && trim($path) !== '') {
            return $this->resolvePath(trim($path));
        }

        $src = $this->srcPath();

        return $src !== null ? $src.'/Resources' : null;
    }

    public function permissionsFile(): ?string
    {
        $path = $this->get('permissions.definitions', 'config/permissions.php');

        return is_string($path) && trim($path) !== ''
            ? $this->resolvePath(trim($path))
            : null;
    }

    public function defaultConfigFile(): ?string
    {
        return $this->resolvePath('config/module.php');
    }

    public function dedicatedConfigOverridePath(string $root): string
    {
        return rtrim($root, '/').'/custom/config/modules/'.$this->slug().'.php';
    }

    public function routeFile(string $area): ?string
    {
        $path = $this->get('routes.'.trim($area));

        if (is_string($path) && trim($path) !== '') {
            return $this->resolvePath(trim($path));
        }

        return null;
    }

    public function bootFiles(): array
    {
        $files = $this->get('boot.files', []);

        if (!is_array($files)) {
            return [];
        }

        $resolved = [];

        foreach ($files as $file) {
            if (!is_string($file) || trim($file) === '') {
                continue;
            }

            $path = $this->resolvePath(trim($file));

            if ($path !== null) {
                $resolved[] = $path;
            }
        }

        return array_values(array_unique($resolved));
    }

    public function resolvePath(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($this->root, '/').'/'.ltrim($path, '/');
    }
}
