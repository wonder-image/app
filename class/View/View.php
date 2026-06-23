<?php

namespace Wonder\View;

use RuntimeException;
use Throwable;
use Wonder\App\LegacyGlobals;

class View
{
    private static array $layoutStack = [];
    private static array $dataStack = [];
    private static array $globalStack = [];

    public function __construct(
        private readonly string $view,
        private readonly array $data = [],
    ) {}

    public static function make(string $view, array $data = []): self
    {
        return new self($view, $data);
    }

    public static function component(string $component, array $data = []): string
    {
        $data = self::normalizeData($data);
        $runtime = self::runtimeData();

        self::$dataStack[] = $data;
        self::$globalStack[] = self::pushGlobals($data);

        ob_start();

        try {
            extract($runtime, EXTR_SKIP);
            extract($data, EXTR_SKIP);

            $ROOT = (string) LegacyGlobals::get('ROOT', '');
            $ROOT_APP = (string) LegacyGlobals::get('ROOT_APP', '');

            include self::resolveComponentPath($component, $ROOT, $ROOT_APP);

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();

            throw $exception;
        } finally {
            self::popGlobals();
            array_pop(self::$dataStack);
        }
    }

    public function render(): void
    {
        $data = self::normalizeData($this->data);
        $runtime = self::runtimeData();

        self::$dataStack[] = $data;
        self::$globalStack[] = self::pushGlobals($data);

        try {
            extract($runtime, EXTR_SKIP);
            extract($data, EXTR_SKIP);
            include $this->view;
        } finally {
            self::popGlobals();
            array_pop(self::$dataStack);
        }
    }

    public static function layout(string $layout, array $data = []): void
    {
        self::$layoutStack[] = [
            'layout' => $layout,
            'data' => $data,
        ];

        ob_start();
    }

    public static function end(): void
    {
        $context = array_pop(self::$layoutStack);

        if (!is_array($context)) {
            throw new RuntimeException('Nessun layout aperto da chiudere.');
        }

        $content = ob_get_clean();
        $layoutData = array_merge(
            self::currentData(),
            is_array($context['data'] ?? null) ? $context['data'] : []
        );

        $layoutData['PAGE_CONTENT'] = $content;

        $ROOT = (string) LegacyGlobals::get('ROOT', '');
        $ROOT_APP = (string) LegacyGlobals::get('ROOT_APP', '');

        extract(self::runtimeData(), EXTR_SKIP);
        extract($layoutData, EXTR_SKIP);

        include self::resolveLayoutPath((string) ($context['layout'] ?? ''), (string) $ROOT, (string) $ROOT_APP);
    }

    private static function runtimeData(): array
    {
        return LegacyGlobals::scope();
    }

    private static function currentData(): array
    {
        $data = end(self::$dataStack);

        return is_array($data) ? $data : [];
    }

    public static function currentSlots(): array
    {
        $data = self::currentData();
        $slots = $data['slots'] ?? null;

        return is_array($slots) ? $slots : [];
    }

    private static function normalizeData(array $data): array
    {
        if (array_key_exists('_POST', $data) && !array_key_exists('VALUES', $data)) {
            $data['VALUES'] = is_array($data['_POST']) ? $data['_POST'] : [];
        }

        return $data;
    }

    private static function pushGlobals(array $data): array
    {
        $snapshot = [];

        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $snapshot[$key] = [
                'exists' => array_key_exists($key, $GLOBALS),
                'value' => $GLOBALS[$key] ?? null,
            ];

            $GLOBALS[$key] = $value;
        }

        return $snapshot;
    }

    private static function popGlobals(): void
    {
        $snapshot = array_pop(self::$globalStack);

        if (!is_array($snapshot)) {
            return;
        }

        foreach ($snapshot as $key => $state) {
            if (($state['exists'] ?? false) === true) {
                $GLOBALS[$key] = $state['value'] ?? null;
                continue;
            }

            unset($GLOBALS[$key]);
        }
    }

    private static function resolveLayoutPath(string $layout, string $root, string $rootApp): string
    {
        $layout = trim($layout);

        if ($layout === '') {
            throw new RuntimeException('Layout non definito.');
        }

        if (file_exists($layout)) {
            return $layout;
        }

        $relativeLayout = str_replace('.', '/', $layout);
        $candidates = [
            $root.'/custom/view/layout/'.$relativeLayout.'.php',
            $root.'/custom/view/layout/'.$relativeLayout.'_layout.php',
            $rootApp.'/view/layout/'.$relativeLayout.'.php',
            $rootApp.'/view/layout/'.$relativeLayout.'_layout.php',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException("Layout non trovato: {$layout}");
    }

    private static function resolveComponentPath(string $component, string $root, string $rootApp): string
    {
        $component = trim($component);

        if ($component === '') {
            throw new RuntimeException('Component non definito.');
        }

        if (file_exists($component)) {
            return $component;
        }

        $relativeComponent = self::normalizeComponentPath($component);
        $candidates = [
            $root.'/custom/view/components/'.$relativeComponent.'.php',
            $rootApp.'/view/components/'.$relativeComponent.'.php',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException("Component non trovato: {$component}");
    }

    private static function normalizeComponentPath(string $component): string
    {
        $component = str_replace(['\\', '.'], '/', trim($component));
        $component = trim($component, '/');

        if ($component === '') {
            return '';
        }

        if (
            str_starts_with($component, 'frontend/')
            || str_starts_with($component, 'backend/')
        ) {
            return $component;
        }

        return 'frontend/'.$component;
    }
}
