<?php

namespace Wonder\Api\Support;

use RuntimeException;
use Wonder\Api\Endpoint;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;
use Wonder\App\Table;

final class ResourceApiController
{
    private string $resourceClass;
    private ResourceApiPresenter $presenter;

    private function __construct(string $resourceClass)
    {
        if (!is_subclass_of($resourceClass, Resource::class)) {
            throw new RuntimeException("{$resourceClass} deve estendere ".Resource::class);
        }

        $this->resourceClass = $resourceClass;
        $this->presenter = new ResourceApiPresenter($resourceClass);
    }

    public static function fromSlug(string $slug): self
    {
        return new self(ResourceRegistry::resolve($slug));
    }

    public function handle(string $action, array $routeParameters = []): array
    {
        $endpoint = $this->endpoint($action);

        return match ($action) {
            'index' => $this->index($endpoint),
            'store' => $this->store($endpoint),
            'show' => $this->show($endpoint, (int) ($routeParameters['id'] ?? 0)),
            'update' => $this->update($endpoint, (int) ($routeParameters['id'] ?? 0)),
            'destroy' => $this->destroy($endpoint, (int) ($routeParameters['id'] ?? 0)),
            default => throw new RuntimeException("Azione resource api non supportata: {$action}"),
        };
    }

    private function index(Endpoint $endpoint): array
    {
        $modelClass = $this->resourceClass::modelClass();
        $limit = $this->presenter->resolveLimit((int) ($endpoint->data['limit'] ?? 0));
        $fields = $this->presenter->fieldsFor('index', ['*']);

        $items = $modelClass::find(
            $this->presenter->indexCondition(),
            $limit,
            $this->presenter->indexOrderColumn(),
            $this->presenter->indexOrderDirection(),
            $fields
        );

        return $endpoint->response($this->presenter->indexPayload($items, $limit));
    }

    private function show(Endpoint $endpoint, int $id): array
    {
        $fields = $this->presenter->fieldsFor('show', ['*']);
        $item = $this->resourceRow($id, $fields);

        return $endpoint->response($this->presenter->showPayload($item));
    }

    private function store(Endpoint $endpoint): array
    {
        $modelClass = $this->resourceClass::modelClass();
        $values = $this->preparedValues($endpoint, 'store');
        $result = $modelClass::create($values);

        if (empty($result->success)) {
            return $endpoint->response(
                $this->presenter->validationErrorPayload((array) ($result->response ?? [])),
                422
            );
        }

        $this->resourceClass::afterStore($result, $values);

        $item = [];
        if (isset($result->insert_id)) {
            $fields = $this->presenter->fieldsFor('show', ['*']);
            $item = $this->resourceRow((int) $result->insert_id, $fields);
        }

        return $endpoint->response($this->presenter->storePayload($item), 201);
    }

    private function update(Endpoint $endpoint, int $id): array
    {
        $modelClass = $this->resourceClass::modelClass();
        $existingValues = $this->resourceRow($id);
        $values = $this->preparedValues($endpoint, 'update', $existingValues);
        $result = $modelClass::update($values, $id);

        if (empty($result->success)) {
            return $endpoint->response(
                $this->presenter->validationErrorPayload((array) ($result->response ?? [])),
                422
            );
        }

        $this->resourceClass::afterUpdate($id, $result, $values);

        $fields = $this->presenter->fieldsFor('show', ['*']);
        $item = $this->resourceRow($id, $fields);

        return $endpoint->response($this->presenter->updatePayload($item));
    }

    private function destroy(Endpoint $endpoint, int $id): array
    {
        $this->resourceRow($id);
        $modelClass = $this->resourceClass::modelClass();
        $result = $modelClass::delete($id);
        $this->resourceClass::afterDelete($id, $result);

        return $endpoint->response($this->presenter->destroyPayload($id, !empty($result->success)));
    }

    private function endpoint(string $action): Endpoint
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $permissions = (array) $this->resourceClass::permissionSchema()->get('api');

        return new Endpoint(
            rtrim($requestPath, '/'),
            $requestMethod,
            $permissions[$action] ?? []
        );
    }

    private function guardPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('ID resource non valido.');
        }
    }

    private function requestValues(Endpoint $endpoint): array
    {
        return array_merge((array) $endpoint->data, $_POST, $_FILES);
    }

    private function preparedValues(Endpoint $endpoint, string $action, ?array $oldValues = null): array
    {
        $tableName = $this->resourceClass::modelTable();
        $rawValues = $this->presenter->writableValues($this->requestValues($endpoint), $action);
        $values = $this->resourceClass::mutateRequestValues($rawValues, $action, 'api', $oldValues);

        LegacyGlobals::set('NAME', (object) [
            'table' => $tableName,
            'folder' => $this->resourceClass::legacyFolder(),
            'schema' => $this->resourceClass::prepareSchemaName(),
        ]);

        $resourceSchemaName = $this->resourceClass::prepareSchemaName();

        if (array_key_exists($resourceSchemaName, Table::$list)) {
            return Table::key($resourceSchemaName)->prepareFor($tableName, $values, $oldValues);
        }

        if (array_key_exists($tableName, Table::$list)) {
            return Table::key($tableName)->prepare($values, $oldValues);
        }

        return $values;
    }

    private function resourceRow(int $id, array|string $fields = ['*']): array
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();
        $row = $modelClass::find($this->resourceConditionForId($id), 1, null, null, $fields);

        if (!is_array($row) || $row === []) {
            throw new RuntimeException('Record resource non trovato.');
        }

        return $row;
    }

    private function resourceConditionForId(int $id): string|array
    {
        $condition = $this->resourceClass::getQuery('condition');

        if (is_array($condition)) {
            $condition['id'] = $id;

            return $condition;
        }

        if (is_string($condition) && trim($condition) !== '') {
            return "`id` = {$id} AND ({$condition})";
        }

        return ['id' => $id];
    }
}
