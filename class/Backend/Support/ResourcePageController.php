<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;
use Wonder\App\Table;
use Wonder\View\View;

final class ResourcePageController
{
    private string $resourceClass;
    private ResourcePagePresenter $presenter;

    private function __construct(string $resourceClass)
    {
        if (!is_subclass_of($resourceClass, Resource::class)) {
            throw new RuntimeException("{$resourceClass} deve estendere ".Resource::class);
        }

        $this->resourceClass = $resourceClass;
        $this->presenter = new ResourcePagePresenter($resourceClass);
    }

    public static function fromSlug(string $slug): self
    {
        return new self(ResourceRegistry::resolve($slug));
    }

    public function handle(string $action, array $routeParameters = []): void
    {
        match ($action) {
            'list' => $this->list(),
            'create' => $this->create(),
            'store' => $this->store(),
            'view' => $this->view((int) ($routeParameters['id'] ?? 0)),
            'edit' => $this->edit((int) ($routeParameters['id'] ?? 0)),
            'update' => $this->update((int) ($routeParameters['id'] ?? 0)),
            'delete' => $this->delete((int) ($routeParameters['id'] ?? 0)),
            default => throw new RuntimeException("Azione resource backend non supportata: {$action}"),
        };
    }

    private function list(): void
    {
        if ($this->resourceClass::isSingleton()) {
            $this->redirectToSingletonEdit();
        }

        View::make(
            $this->presenter->viewPath('list'),
            $this->presenter->list($this->renderListTable())
        )->render();
    }

    private function create(): void
    {
        $this->renderForm('create', []);
    }

    private function store(): void
    {
        global $ALERT;

        $ALERT = '';
        $modelClass = $this->resourceClass::modelClass();
        $values = $this->preparedValues();
        $result = (object) ['success' => false];

        if (empty($ALERT)) {
            $result = $modelClass::query()->Insert($modelClass::$table, $values);
        }

        if (!empty($result->success)) {
            $this->resourceClass::afterStore($result, $values);
            $this->redirectToConfiguredPage('store');
        }

        $errors = !empty($ALERT) ? ['alert' => (string) $ALERT] : (array) ($result->response ?? []);
        $this->renderForm('create', $values, $errors);
    }

    private function view(int $id): void
    {
        $item = $this->resourceRow($id);

        View::make(
            $this->presenter->viewPath('show'),
            $this->presenter->show($item)
        )->render();
    }

    private function edit(int $id): void
    {
        $this->renderForm('edit', $this->resourceRow($id), [], $id);
    }

    private function update(int $id): void
    {
        global $ALERT;

        $this->guardPositiveId($id);
        $ALERT = '';
        $modelClass = $this->resourceClass::modelClass();
        $existingValues = $this->resourceRow($id);
        $values = $this->preparedValues($existingValues);
        $result = (object) ['success' => false];

        if (empty($ALERT)) {
            $result = $modelClass::query()->Update($modelClass::$table, $values, 'id', $id);
        }

        if (!empty($result->success)) {
            $this->resourceClass::afterUpdate($id, $result, $values);
            $this->redirectToConfiguredPage('update');
        }

        $errors = !empty($ALERT) ? ['alert' => (string) $ALERT] : (array) ($result->response ?? []);
        $this->renderForm('edit', $values, $errors, $id);
    }

    private function delete(int $id): never
    {
        $this->resourceRow($id);
        $modelClass = $this->resourceClass::modelClass();
        $result = $modelClass::delete($id);
        $this->resourceClass::afterDelete($id, $result);

        $this->redirectToConfiguredPage('delete');
    }

    private function renderForm(string $mode, array $values = [], array $errors = [], ?int $id = null): void
    {
        View::make(
            $this->presenter->viewPath('form'),
            $this->presenter->form($mode, $values, $errors, $id)
        )->render();
    }

    private function requestValues(): array
    {
        return array_merge($_POST, $_FILES);
    }

    private function preparedValues(?array $oldValues = null): array
    {
        $tableName = $this->resourceClass::modelTable();
        LegacyGlobals::set('NAME', $this->presenter->legacyName());
        $requestValues = $this->resourceClass::mutateRequestValues(
            $this->requestValues(),
            $oldValues === null ? 'store' : 'update',
            'backend',
            $oldValues
        );

        $resourceSchemaName = $this->resourceClass::prepareSchemaName();

        if (array_key_exists($resourceSchemaName, Table::$list)) {
            return Table::key($resourceSchemaName)->prepareFor($tableName, $requestValues, $oldValues);
        }

        if (array_key_exists($tableName, Table::$list)) {
            return Table::key($tableName)->prepare($requestValues, $oldValues);
        }

        return $requestValues;
    }

    private function renderListTable(): string
    {
        try {
            return ResourceTableRenderer::render($this->resourceClass);
        } catch (Throwable $exception) {
            return $this->presenter->listFallback($exception);
        }
    }

    private function redirectToConfiguredPage(string $action): never
    {
        header('Location: '.$this->presenter->redirectUrl($action));
        exit();
    }

    private function guardPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('ID resource non valido.');
        }
    }

    private function redirectToSingletonEdit(): never
    {
        $id = $this->resourceClass::singletonRecordId();

        if ($id === null || $id === '') {
            throw new RuntimeException('Record singleton non configurato.');
        }

        header('Location: '.__r('backend.resource.'.$this->resourceClass::slug().'.edit', ['id' => $id]));
        exit();
    }

    private function resourceRow(int $id): array
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();
        $row = $modelClass::find($this->resourceConditionForId($id), 1);

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
