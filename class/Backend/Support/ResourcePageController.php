<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Throwable;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;
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
        $modelClass = $this->resourceClass::modelClass();
        $values = $this->requestValues();
        $result = $modelClass::create($values);

        if (!empty($result->success)) {
            $this->redirectToConfiguredPage('store');
        }

        $this->renderForm('create', $values, (array) ($result->response ?? []));
    }

    private function view(int $id): void
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();

        View::make(
            $this->presenter->viewPath('show'),
            $this->presenter->show((array) $modelClass::findById($id))
        )->render();
    }

    private function edit(int $id): void
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();

        $this->renderForm('edit', (array) $modelClass::findById($id), [], $id);
    }

    private function update(int $id): void
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();
        $values = $this->requestValues();
        $result = $modelClass::update($values, $id);

        if (!empty($result->success)) {
            $this->redirectToConfiguredPage('update');
        }

        $this->renderForm('edit', $values, (array) ($result->response ?? []), $id);
    }

    private function delete(int $id): never
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();
        $modelClass::delete($id);

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
}
