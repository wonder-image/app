<?php

namespace Wonder\App\Module\Contracts;

interface ModuleInterface
{
    public static function root(): string;

    public static function manifestPath(): string;

    public static function handlerPath(string $path): string;

    public static function viewPath(string $path): string;

    public static function langPath(): string;

    public static function assetPath(string $path = ''): string;
}
