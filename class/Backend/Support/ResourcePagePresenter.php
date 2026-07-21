<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;

final class ResourcePagePresenter
{
    public function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }
    }

    public function list(string $tableHtml): array
    {
        return [
            'TITLE' => $this->pageTitle('list'),
            'RESOURCE_CLASS' => $this->resourceClass,
            'TABLE_HTML' => $tableHtml,
            'USER' => $this->viewUser(),
        ];
    }

    public function form(string $mode, array $values = [], array $errors = [], ?int $id = null): array
    {
        $formSchema = $this->resourceClass::formSchema();
        $formLayout = $this->resourceClass::formLayoutSchema();
        $values = $this->resourceClass::mutateFormValues($values, $mode, 'backend');

        return [
            'TITLE' => $this->pageTitle($mode),
            'RESOURCE_CLASS' => $this->resourceClass,
            'FIELDS' => $this->hydrateFields($formSchema, $values, $errors, $mode),
            'SIDEBAR_FIELDS' => [],
            'FORM_LAYOUT' => $this->hydrateLayout($formLayout, $values, $errors, $mode),
            'FORM_METHOD' => 'POST',
            'FORM_ENCTYPE' => 'multipart/form-data',
            'FORM_ACTION' => $mode === 'edit' && $id !== null
                ? $this->editUrl($id)
                : $this->createUrl(),
            'BACK_URL' => $this->backUrl(),
            'FORM_ERRORS' => $errors,
            'USER' => $this->viewUser(),
            'VALUES' => $values,
            'NAME' => $this->legacyName(),
        ];
    }

    public function show(array $item): array
    {
        return [
            'TITLE' => $this->pageTitle('view'),
            'SUBTITLE' => $this->pageSubtitle('view'),
            'ACTIONS' => $this->pageActions('view', $item),
            'RESOURCE_CLASS' => $this->resourceClass,
            'ITEM' => $item,
            'BACK_URL' => $this->backUrl(),
            'USER' => $this->viewUser(),
        ];
    }

    public function viewPath(string $view): string
    {
        $views = (array) $this->resourceClass::pageSchema()->get('views');
        $customView = $views[$view === 'show' ? 'show' : $view] ?? null;

        if (is_string($customView) && $customView !== '') {
            return $customView;
        }

        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');

        return $rootApp.'/view/pages/backend/resource/'.$view.'.php';
    }

    public function redirectUrl(string $action): string
    {
        $redirects = (array) $this->resourceClass::pageSchema()->get('redirects');
        $page = (string) ($redirects[$action] ?? 'list');

        return match ($page) {
            'create' => $this->createPageUrl(),
            default => $this->listUrl(),
        };
    }

    public function listFallback(Throwable $exception): string
    {
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<wi-card class="col-12">
    <div class="alert alert-warning mb-0">
        La tabella non può essere caricata senza una connessione database valida.<br>
        <small class="text-body-secondary">{$message}</small>
    </div>
</wi-card>
HTML;
    }

    public function legacyName(): object
    {
        return (object) [
            'table' => $this->resourceClass::modelTable(),
            'folder' => $this->resourceClass::legacyFolder(),
            'schema' => $this->resourceClass::prepareSchemaName(),
        ];
    }

    private function hydrateFields(array $fields, array $values, array $errors, string $mode): array
    {
        $hydrated = [];

        foreach ($fields as $field) {
            if (!is_object($field)) {
                continue;
            }

            $clone = $this->applyModelFieldState(clone $field, $mode);
            $name = property_exists($clone, 'name') ? (string) ($clone->name ?? '') : '';

            if ($name !== '' && array_key_exists($name, $values) && method_exists($clone, 'value')) {
                $clone->value($values[$name]);
            }

            if ($name !== '' && isset($errors[$name]) && method_exists($clone, 'error')) {
                $error = $errors[$name];
                $message = is_object($error) && property_exists($error, 'message')
                    ? (string) ($error->message ?? '')
                    : (is_string($error) ? $error : '');

                if ($message !== '') {
                    $clone->error($message);
                }
            }

            $hydrated[] = $clone;
        }

        return $hydrated;
    }

    private function hydrateLayout(mixed $layout, array $values, array $errors, string $mode): mixed
    {
        if (!is_object($layout)) {
            return null;
        }

        $clone = clone $layout;

        if (!property_exists($clone, 'components') || !is_array($clone->components ?? null)) {
            return $this->hydrateField($clone, $values, $errors, $mode);
        }

        $components = [];

        foreach ($clone->components as $component) {
            $components[] = $this->hydrateLayout($component, $values, $errors, $mode);
        }

        $clone->components = $components;

        return $clone;
    }

    private function hydrateField(object $field, array $values, array $errors, string $mode): object
    {
        $clone = $this->applyModelFieldState(clone $field, $mode);
        $name = property_exists($clone, 'name') ? (string) ($clone->name ?? '') : '';

        if ($name !== '' && array_key_exists($name, $values) && method_exists($clone, 'value')) {
            $clone->value($values[$name]);
        }

        if ($name !== '' && isset($errors[$name]) && method_exists($clone, 'error')) {
            $error = $errors[$name];
            $message = is_object($error) && property_exists($error, 'message')
                ? (string) ($error->message ?? '')
                : (is_string($error) ? $error : '');

            if ($message !== '') {
                $clone->error($message);
            }
        }

        return $clone;
    }

    private function applyModelFieldState(object $field, string $mode): object
    {
        if ($mode !== 'edit' || !property_exists($field, 'name') || !method_exists($field, 'readonly')) {
            return $field;
        }

        $name = trim((string) ($field->name ?? ''));

        if ($name === '') {
            return $field;
        }

        $modelFields = $this->resourceClass::modelClass()::dataFields();
        $modelField = $modelFields[$name] ?? null;

        if ($modelField === null || !method_exists($modelField, 'getSchema')) {
            return $field;
        }

        if (($modelField->getSchema('readonly_on_update') ?? false) === true) {
            $field->readonly();
        }

        return $field;
    }

    private function pageTitle(string $page): string
    {
        $titles = (array) $this->resourceClass::pageSchema()->get('titles');

        return (string) ($titles[$page] ?? $this->resourceClass::titleLabel());
    }

    private function pageSubtitle(string $page): string
    {
        $subtitles = (array) $this->resourceClass::pageSchema()->get('subtitles');

        return (string) ($subtitles[$page] ?? '');
    }

    /**
     * Risolve i bottoni dell'header per una pagina.
     *
     * La normalizzazione (bottoni piatti e bottoni con dropdown) è delegata
     * a {@see PageActionNormalizer}. Salta silenziosamente descriptor mal
     * formati invece di lanciare eccezioni — un bottone "vuoto" durante una
     * view non deve abbattere la pagina.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pageActions(string $page, array $item = []): array
    {
        $registered = (array) $this->resourceClass::pageSchema()->get('actions');
        $entry = $registered[$page] ?? null;

        if ($entry === null) {
            return [];
        }

        try {
            $descriptors = is_callable($entry) ? $entry($item) : $entry;
        } catch (Throwable) {
            return [];
        }

        if (!is_array($descriptors)) {
            return [];
        }

        return PageActionNormalizer::normalize($descriptors);
    }

    private function listUrl(): string
    {
        return __r('backend.resource.'.$this->resourceClass::slug().'.list');
    }

    private function createUrl(): string
    {
        return __r('backend.resource.'.$this->resourceClass::slug().'.store');
    }

    private function createPageUrl(): string
    {
        return __r('backend.resource.'.$this->resourceClass::slug().'.create');
    }

    private function editUrl(int $id): string
    {
        return __r('backend.resource.'.$this->resourceClass::slug().'.update', ['id' => $id]);
    }

    private function viewUser(): object
    {
        $user = LegacyGlobals::get('USER');

        if (is_object($user) && isset($user->authority) && is_array($user->authority)) {
            return $user;
        }

        return (object) [ 'authority' => [] ];
    }

    private function backUrl(): string
    {
        if ($this->resourceClass::isSingleton()) {
            return '';
        }

        return $this->listUrl();
    }
}
