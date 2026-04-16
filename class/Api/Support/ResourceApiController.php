<?php

namespace Wonder\Api\Support;

use RuntimeException;
use Wonder\Api\Endpoint;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;

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
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();
        $fields = $this->presenter->fieldsFor('show', ['*']);
        $item = $modelClass::find(['id' => $id], 1, null, null, $fields);

        return $endpoint->response($this->presenter->showPayload($item));
    }

    private function store(Endpoint $endpoint): array
    {
        $modelClass = $this->resourceClass::modelClass();
        $values = $this->presenter->writableValues((array) $endpoint->data, 'store');
        $result = $modelClass::create($values);

        if (empty($result->success)) {
            return $endpoint->response(
                $this->presenter->validationErrorPayload((array) ($result->response ?? [])),
                422
            );
        }

        $item = [];
        if (isset($result->insert_id)) {
            $fields = $this->presenter->fieldsFor('show', ['*']);
            $item = (array) $modelClass::find(['id' => (int) $result->insert_id], 1, null, null, $fields);
        }

        return $endpoint->response($this->presenter->storePayload($item), 201);
    }

    private function update(Endpoint $endpoint, int $id): array
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();
        $values = $this->presenter->writableValues((array) $endpoint->data, 'update');
        $result = $modelClass::update($values, $id);

        if (empty($result->success)) {
            return $endpoint->response(
                $this->presenter->validationErrorPayload((array) ($result->response ?? [])),
                422
            );
        }

        $fields = $this->presenter->fieldsFor('show', ['*']);
        $item = (array) $modelClass::find(['id' => $id], 1, null, null, $fields);

        return $endpoint->response($this->presenter->updatePayload($item));
    }

    private function destroy(Endpoint $endpoint, int $id): array
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();
        $result = $modelClass::delete($id);

        return $endpoint->response($this->presenter->destroyPayload($id, !empty($result->success)));
    }

    private function endpoint(string $action): Endpoint
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $permissions = (array) ($this->resourceClass::permissionSchema()['api'] ?? []);

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
}
