<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceRegistry;
use Wonder\App\Resources\Support\UserManagementResource;
use Wonder\View\View;

final class UserManagementPageController
{
    private function __construct(
        private readonly string $resourceClass,
        private readonly string $slug,
    ) {
    }

    public static function fromSlug(string $slug): self
    {
        $resourceClass = ResourceRegistry::resolve($slug);

        if (!is_subclass_of($resourceClass, UserManagementResource::class)) {
            throw new RuntimeException("{$resourceClass} non usa il controller user condiviso.");
        }

        return new self($resourceClass, $slug);
    }

    public function handle(string $action, array $parameters = []): void
    {
        $id = isset($parameters['id']) ? (int) $parameters['id'] : null;

        if (in_array($action, ['store', 'update'], true)) {
            $this->submit($action === 'update' ? 'edit' : 'create', $id);

            return;
        }

        $this->render($action === 'edit' ? 'edit' : 'create', $id);
    }

    private function submit(string $mode, ?int $id): void
    {
        global $ALERT;

        $modifyId = $id;

        if ($modifyId === null && !empty($_POST['user_id'])) {
            $modifyId = (int) $_POST['user_id'];
        }

        $post = array_merge($_POST, $_FILES);
        $upload = user($post, $modifyId);
        $values = isset($upload->values) && is_array($upload->values) ? $upload->values : $post;

        if (empty($ALERT) && $mode === 'create' && $this->resourceClass::sendsWelcomeMail()) {
            $this->sendWelcomeMail($post);
        }

        if (empty($ALERT)) {
            $route = isset($_POST['upload-add'])
                ? __r('backend.resource.'.$this->slug.'.create')
                : __r('backend.resource.'.$this->slug.'.list');

            header('Location: '.$route);
            exit();
        }

        $this->render($mode, $id, $values);
    }

    private function render(string $mode, ?int $id = null, ?array $values = null): void
    {
        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');

        if ($rootApp === '') {
            throw new RuntimeException('ROOT_APP non disponibile.');
        }

        $values = $values ?? ($mode === 'edit' && $id !== null ? $this->loadValues($id) : []);
        $selectedAuthority = $this->resourceClass::selectedAuthority($values);
        $title = $mode === 'edit'
            ? 'Modifica '.$this->resourceClass::label()
            : 'Aggiungi '.$this->resourceClass::label();

        View::make($rootApp.'/view/pages/backend/user/manage.php', [
            'TITLE' => $title,
            'BACK_URL' => __r('backend.resource.'.$this->slug.'.list'),
            'FORM_ACTION' => $mode === 'edit' && $id !== null
                ? __r('backend.resource.'.$this->slug.'.update', ['id' => $id])
                : __r('backend.resource.'.$this->slug.'.store'),
            'VALUES' => $values,
            'RESOURCE_CLASS' => $this->resourceClass,
            'RESOURCE_SLUG' => $this->slug,
            'RESOURCE_MODE' => $mode,
            'USER_AREA' => $this->resourceClass::managedArea(),
            'COLOR_OPTIONS' => $this->colorOptions(),
            'AUTHORITY_OPTIONS' => $this->resourceClass::availableAuthorities(),
            'SELECTED_AUTHORITY' => $selectedAuthority,
            'EXISTING_USER_OPTIONS' => $this->resourceClass::existingUserOptions(),
            'ALLOW_EXISTING_USER' => $mode === 'create' && $this->resourceClass::canCreateFromExisting(),
            'SHOW_API_FIELDS' => $this->resourceClass::managedArea() === 'api',
            'NAME' => (object) [
                'table' => 'user',
                'folder' => 'user',
            ],
        ])->render();
    }

    private function loadValues(int $id): array
    {
        $values = (array) sqlSelect('user', ['id' => $id], 1)->row;

        if ($this->resourceClass::managedArea() === 'api') {
            $values = array_merge(
                $values,
                (array) sqlSelect('api_users', ['user_id' => $id], 1)->row
            );
        }

        return $values;
    }

    private function colorOptions(): array
    {
        $field = $this->resourceClass::getInput('color');

        return (array) $field->get('options');
    }

    private function sendWelcomeMail(array $post): void
    {
        $username = trim((string) ($post['username'] ?? ''));
        $email = trim((string) ($post['email'] ?? ''));
        $name = trim((string) ($post['name'] ?? ''));
        $password = trim((string) ($post['password'] ?? ''));
        $authorityKey = trim((string) ($post['authority'] ?? ''));

        if ($username === '' || $email === '' || $password === '' || $authorityKey === '') {
            return;
        }

        $authority = permissions($authorityKey);
        $authorityName = is_object($authority) ? (string) ($authority->name ?? $authorityKey) : $authorityKey;
        $backendPath = (string) LegacyGlobals::get('PATH')->backend;

        $content = "
        Ciao {$name}, benvenuto/a nello staff. <br>
        <br>
        Queste sono le tue credenziali: <br>
        Link accesso: <a href='{$backendPath}'>Clicca qui</a> <br>
        Username: <b>{$username}</b> <br>
        Password: <b>{$password}</b> <br>
        <br>
        Autorizzazione: <b>{$authorityName}</b>
        ";

        sendMail('info@wonderimage.it', $email, 'Benvenuto nello staff', $content);
    }
}
