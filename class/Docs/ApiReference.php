<?php

namespace Wonder\Docs;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * L'elenco dei metodi pubblici di un Element, letto con la reflection: firma e
 * prima riga del docblock. I metodi propri della classe vengono prima, quelli
 * ereditati da classi madri e trait dopo, raggruppati per origine. Getter,
 * `render()` e la gestione interna dello schema restano fuori.
 */
final class ApiReference
{
    private const HIDDEN = ['render', 'toArray', 'schema', 'schemaPush', 'getSchema', 'attributes'];

    private const HIDDEN_FROM = ['Wonder\\Concerns\\HasSchema'];

    /**
     * @return array{constructor: ?array<string, mixed>, own: array<int, array<string, mixed>>, inherited: array<string, array<int, array<string, mixed>>>}
     */
    public static function for(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $own = [];
        $inherited = [];
        $constructor = null;

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if ($name === '__construct') {
                $constructor = self::describe($method, $reflection);
                continue;
            }

            if (self::hidden($method)) {
                continue;
            }

            $origin = self::origin($method, $reflection);
            $entry = self::describe($method, $reflection);

            if ($origin === $reflection->getName()) {
                $own[] = $entry;
                continue;
            }

            $inherited[self::shortName($origin)][] = $entry;
        }

        usort($own, static fn (array $a, array $b): int => [$a['static'] ? 0 : 1, $a['name']] <=> [$b['static'] ? 0 : 1, $b['name']]);

        foreach ($inherited as &$methods) {
            usort($methods, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        }

        unset($methods);
        ksort($inherited);

        return ['constructor' => $constructor, 'own' => $own, 'inherited' => $inherited];
    }

    /** @return array<string, mixed> */
    private static function describe(ReflectionMethod $method, ReflectionClass $class): array
    {
        return [
            'name' => $method->getName(),
            'static' => $method->isStatic(),
            'parameters' => implode(', ', array_map(
                static fn (ReflectionParameter $parameter): string => self::parameter($parameter),
                $method->getParameters()
            )),
            'returns' => self::type($method->getReturnType()),
            'summary' => self::summary($method->getDocComment() ?: ''),
            'origin' => self::shortName(self::origin($method, $class)),
            'deprecated' => str_contains((string) $method->getDocComment(), '@deprecated'),
        ];
    }

    private static function hidden(ReflectionMethod $method): bool
    {
        $name = $method->getName();

        if (str_starts_with($name, '__') || in_array($name, self::HIDDEN, true)) {
            return true;
        }

        if (preg_match('/^(get|has|is)[A-Z]/', $name)) {
            return true;
        }

        foreach (self::HIDDEN_FROM as $hiddenTrait) {
            if (trait_exists($hiddenTrait) && (new ReflectionClass($hiddenTrait))->hasMethod($name)
                && $method->getFileName() === (new ReflectionClass($hiddenTrait))->getFileName()) {
                return true;
            }
        }

        return false;
    }

    /** La classe o il trait in cui il metodo è scritto davvero. */
    private static function origin(ReflectionMethod $method, ReflectionClass $class): string
    {
        $declaring = $method->getDeclaringClass();
        $file = $method->getFileName();

        if ($file === $declaring->getFileName()) {
            return $declaring->getName();
        }

        foreach (self::traitsOf($declaring) as $trait) {
            if ($trait->hasMethod($method->getName()) && $trait->getFileName() === $file) {
                return $trait->getName();
            }
        }

        return $declaring->getName();
    }

    /** @return ReflectionClass[] */
    private static function traitsOf(ReflectionClass $class): array
    {
        $traits = [];
        $cursor = $class;

        while ($cursor !== false) {
            foreach ($cursor->getTraits() as $trait) {
                $traits[] = $trait;

                foreach (self::traitsOf($trait) as $nested) {
                    $traits[] = $nested;
                }
            }

            $cursor = $cursor->getParentClass();
        }

        return $traits;
    }

    private static function parameter(ReflectionParameter $parameter): string
    {
        $type = self::type($parameter->getType());
        $signature = ($type !== '' ? $type.' ' : '')
            .($parameter->isPassedByReference() ? '&' : '')
            .($parameter->isVariadic() ? '...' : '')
            .'$'.$parameter->getName();

        if ($parameter->isDefaultValueAvailable()) {
            $signature .= ' = '.self::defaultValue($parameter);
        }

        return $signature;
    }

    private static function defaultValue(ReflectionParameter $parameter): string
    {
        if ($parameter->isDefaultValueConstant()) {
            $constant = (string) $parameter->getDefaultValueConstantName();

            return str_contains($constant, '::') ? substr($constant, (int) strrpos($constant, '\\') + 1) : $constant;
        }

        $value = $parameter->getDefaultValue();

        if (is_array($value)) {
            return $value === [] ? '[]' : '[...]';
        }

        if (is_string($value)) {
            return "'".addcslashes($value, "'\\")."'";
        }

        return str_replace(['NULL', 'TRUE', 'FALSE'], ['null', 'true', 'false'], var_export($value, true));
    }

    private static function type(?ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(static fn (ReflectionNamedType $named): string => self::shortName($named->getName()), $type->getTypes()));
        }

        if ($type instanceof ReflectionNamedType) {
            $name = self::shortName($type->getName());

            return ($type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '?' : '').$name;
        }

        return (string) $type;
    }

    private static function summary(string $docblock): string
    {
        if ($docblock === '') {
            return '';
        }

        $lines = [];

        foreach (preg_split('/\R/', $docblock) ?: [] as $line) {
            $line = trim((string) preg_replace('/^\s*(\/\*\*|\*\/|\*)\s?/', '', $line));

            if ($line === '' && $lines !== []) {
                break;
            }

            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }

            $lines[] = $line;
        }

        return implode(' ', $lines);
    }

    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
