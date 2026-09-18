<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;
use Wonder\App\Table;
use Wonder\Backend\Support\FlashAlert;
use Wonder\Backend\Support\ReadonlyFields;
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
            'form' => $this->formPage(),
            'submit' => $this->submitFormPage(),
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

        $this->guardWritable();

        $ALERT = '';
        $modelClass = $this->resourceClass::modelClass();
        $existingValues = $this->resourceClass::findStoreExistingValues($this->requestValues(), 'backend');
        $targetId = (int) ($existingValues['id'] ?? 0);
        $values = $this->preparedValues($existingValues);
        $result = (object) ['success' => false];

        if (empty($ALERT)) {
            if ($targetId > 0) {
                $result = $modelClass::query()->Update($modelClass::$table, $values, 'id', $targetId);
            } else {
                $result = $modelClass::query()->Insert($modelClass::$table, $values);
            }
        }

        if (!empty($result->success)) {
            if ($targetId > 0) {
                $this->resourceClass::syncRepeaterRelations(
                    $targetId,
                    $_POST,
                    $_FILES,
                    'update',
                    'backend'
                );
                $this->resourceClass::afterUpdate($targetId, $result, $values);
            } else {
                $insertId = (int) ($result->insert_id ?? 0);

                if ($insertId > 0) {
                    $this->resourceClass::syncRepeaterRelations(
                        $insertId,
                        $_POST,
                        $_FILES,
                        'store',
                        'backend'
                    );
                }

                $this->resourceClass::afterStore($result, $values);

                // Vedi nota in ResourceApiController::store(): hook GDPR
                // generico che intercetta i campi `accept_*` posted, anche
                // se non whitelist nello schema della Resource.
                recordResourceConsents(
                    array_merge((array) $_POST, (array) $values),
                    [
                        'source' => 'admin',
                        'ui_surface' => $this->resourceClass::slug().'/store',
                        'subject_ref_type' => $this->resourceClass::modelTable(),
                        'subject_ref_id' => $insertId,
                    ]
                );
            }

            $this->resourceClass::exportSyncData();
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
        $values = $this->resourceClass::isSingleton()
            ? ($this->resourceRowOrNull($id) ?? [])
            : $this->resourceRow($id);

        $this->renderForm('edit', $values, [], $id);
    }

    private function update(int $id): void
    {
        global $ALERT;

        $editable = $this->editableWhenReadonly();
        $readonly = $this->resourceClass::isReadonly();

        if (!ReadonlyFields::allowsUpdate($readonly, $editable)) {
            throw new RuntimeException('Resource in sola lettura in questo ambiente: '.$this->resourceClass::slug());
        }

        $this->guardPositiveId($id);
        $ALERT = '';
        $modelClass = $this->resourceClass::modelClass();
        $existingValues = $this->resourceClass::isSingleton()
            ? $this->resourceRowOrNull($id)
            : $this->resourceRow($id);
        $values = $this->preparedValues($existingValues);
        $result = (object) ['success' => false];

        if (empty($ALERT)) {
            if ($existingValues === null && $this->resourceClass::isSingleton()) {
                $result = $modelClass::query()->Insert($modelClass::$table, array_merge(['id' => $id], $values));
            } else {
                $result = $modelClass::query()->Update($modelClass::$table, $values, 'id', $id);
            }
        }

        if (!empty($result->success)) {
            $this->resourceClass::syncRepeaterRelations(
                $id,
                $readonly ? ReadonlyFields::filter($_POST, $editable) : $_POST,
                $readonly ? ReadonlyFields::filter($_FILES, $editable) : $_FILES,
                'update',
                'backend'
            );
            $this->resourceClass::afterUpdate($id, $result, $values);
            $this->resourceClass::exportSyncData();
            $this->redirectToConfiguredPage('update');
        }

        $errors = !empty($ALERT) ? ['alert' => (string) $ALERT] : (array) ($result->response ?? []);
        $this->renderForm('edit', $values, $errors, $id);
    }

    private function delete(int $id): never
    {
        $this->guardWritable();
        $values = $this->resourceRow($id);
        $result = $this->resourceClass::deleteRecord($id);
        $this->resourceClass::afterDelete($id, $result, $values);
        $this->resourceClass::exportSyncData();

        $this->redirectToConfiguredPage('delete');
    }

    /** Pagina fatta di un solo form (`Resource::isFormPage()`). */
    private function formPage(): void
    {
        $key = 'wi_form_page_message_'.$this->resourceClass::slug();
        $message = (string) ($_SESSION[$key] ?? '');
        unset($_SESSION[$key]);

        View::make(
            $this->presenter->viewPath('form'),
            $this->presenter->formPage($message)
        )->render();
    }

    /** Salvataggio della pagina-form: la Resource fa il lavoro e detta il messaggio. */
    private function submitFormPage(): never
    {
        if ($this->resourceClass::isReadonly()) {
            http_response_code(403);
            exit($this->resourceClass::readonlyNotice());
        }

        $message = $this->resourceClass::submitFormPage($this->requestValues());

        if (trim($message) !== '') {
            $_SESSION['wi_form_page_message_'.$this->resourceClass::slug()] = $message;
            FlashAlert::saved($message);
        } else {
            FlashAlert::code(650);
        }

        header('Location: '.__r('backend.resource.'.$this->resourceClass::slug().'.form'));
        exit();
    }

    private function renderForm(string $mode, array $values = [], array $errors = [], ?int $id = null): void
    {
        $values = $this->resourceClass::hydrateRepeaterFormValues(
            $values,
            $id,
            $_POST,
            $_FILES
        );

        View::make(
            $this->presenter->viewPath('form'),
            $this->presenter->form($mode, $values, $errors, $id)
        )->render();
    }

    private function requestValues(): array
    {
        $values = array_merge($_POST, $_FILES);

        // In sola lettura passano solo i campi dichiarati modificabili: il
        // resto della scheda arriva dal deploy e non si tocca da qui.
        if (!$this->resourceClass::isReadonly()) {
            return $values;
        }

        return ReadonlyFields::filter($values, $this->editableWhenReadonly());
    }

    /** @return list<string> */
    private function editableWhenReadonly(): array
    {
        return ReadonlyFields::normalize($this->resourceClass::editableWhenReadonly());
    }

    private function preparedValues(?array $oldValues = null): array
    {
        $tableName = $this->resourceClass::modelTable();
        LegacyGlobals::set('NAME', $this->presenter->legacyName());
        $requestValues = $this->resourceClass::mutateRequestValues(
            $this->resourceClass::stripRelationInputValues($this->requestValues()),
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
        // Salvataggio o eliminazione andati a buon fine: il toast lo mostra
        // la pagina dove arriviamo dopo il redirect.
        FlashAlert::code(650);

        header('Location: '.$this->presenter->redirectUrl($action));
        exit();
    }

    private function guardPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('ID resource non valido.');
        }
    }

    private function guardWritable(): void
    {
        if ($this->resourceClass::isReadonly()) {
            throw new RuntimeException('Resource in sola lettura in questo ambiente: '.$this->resourceClass::slug());
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
        $row = $this->resourceRowOrNull($id);

        if (!is_array($row) || $row === []) {
            throw new RuntimeException('Record resource non trovato.');
        }

        return $row;
    }

    private function resourceRowOrNull(int $id): ?array
    {
        $this->guardPositiveId($id);
        $modelClass = $this->resourceClass::modelClass();
        $row = $modelClass::find($this->resourceConditionForId($id), 1);

        if (!is_array($row) || $row === []) {
            return null;
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
