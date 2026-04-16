<?php

namespace Wonder\Api\Support;

use RuntimeException;
use Wonder\App\Resource;

final class ResourceApiPresenter
{
    private array $apiSchema;
    private array $querySchema;

    public function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->apiSchema = $this->resourceClass::apiSchema();
        $this->querySchema = $this->resourceClass::querySchema();
    }

    public function indexPayload(mixed $items, int $limit): array
    {
        return [
            'items' => $this->collection($items),
            'meta' => [
                'limit' => $limit,
                'resource' => $this->resourceClass::slug(),
            ],
        ];
    }

    public function showPayload(mixed $item): array
    {
        return [
            'item' => $this->item($item),
        ];
    }

    public function storePayload(mixed $item): array
    {
        return [
            'item' => $this->item($item),
        ];
    }

    public function updatePayload(mixed $item): array
    {
        return [
            'item' => $this->item($item),
        ];
    }

    public function destroyPayload(int $id, bool $deleted): array
    {
        return [
            'deleted' => $deleted,
            'id' => $id,
        ];
    }

    public function validationErrorPayload(array $errors): array
    {
        return [
            'message' => 'Validation failed.',
            'errors' => $this->normalizeErrors($errors),
        ];
    }

    public function writableValues(array $values, string $action): array
    {
        $allowed = $this->fieldsFor($action, []);

        if (empty($allowed)) {
            return [];
        }

        return array_intersect_key($values, array_flip($allowed));
    }

    public function fieldsFor(string $action, array $fallback = ['*']): array
    {
        $fields = (array) ($this->apiSchema['fields'][$action] ?? []);

        return !empty($fields) ? $fields : $fallback;
    }

    public function indexCondition(): string|array|null
    {
        return $this->querySchema['condition'] ?? null;
    }

    public function indexOrderColumn(): string
    {
        return (string) ($this->querySchema['order']['column'] ?? 'creation');
    }

    public function indexOrderDirection(): string
    {
        return (string) ($this->querySchema['order']['direction'] ?? 'DESC');
    }

    public function resolveLimit(int $requestedLimit): int
    {
        $pagination = (array) ($this->apiSchema['pagination'] ?? []);
        $defaultLimit = (int) ($pagination['default_limit'] ?? 25);
        $maxLimit = (int) ($pagination['max_limit'] ?? 100);

        if ($requestedLimit <= 0) {
            $requestedLimit = $defaultLimit;
        }

        return min($requestedLimit, max(1, $maxLimit));
    }

    private function collection(mixed $items): array
    {
        if (!is_array($items) || $items === []) {
            return [];
        }

        $isList = array_keys($items) === range(0, count($items) - 1);

        if ($isList) {
            return array_values(array_filter($items, 'is_array'));
        }

        return [ $items ];
    }

    private function item(mixed $item): array
    {
        return is_array($item) ? $item : [];
    }

    private function normalizeErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $key => $error) {
            $field = str_contains((string) $key, '_')
                ? preg_replace('/^[^a-zA-Z0-9]*|^.*?_/', '', (string) $key)
                : (string) $key;

            if (is_object($error) && property_exists($error, 'message')) {
                $normalized[$field] = (string) ($error->message ?? '');
                continue;
            }

            if (is_string($error)) {
                $normalized[$field] = $error;
            }
        }

        return $normalized;
    }
}
