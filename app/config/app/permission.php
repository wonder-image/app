<?php

    use Wonder\App\Permission\{Permissions, Permission, Area};

    Permissions::reset()
        ->addArea(Area::make('backend'))
        ->addArea(Area::make('frontend'))
        ->addArea(Area::make('api'))
        ->addPermission(
            Permission::make('admin', 'backend')
                ->name('Admin')
                ->icon("<i class='bi bi-arrow-through-heart'></i>")
                ->bg('bg-primary')
                ->tx('text-light')
                ->color('primary')
                ->creator(['admin'])
        )
        ->addPermission(
            Permission::make('administrator', 'backend')
                ->name('Amministratore')
                ->icon("<i class='bi bi-person-hearts'></i>")
                ->bg('bg-light')
                ->tx('text-dark')
                ->color('light')
                ->creator(['admin'])
        )
        ->addPermission(
            Permission::make('api_user', 'backend')
                ->name('Api')
                ->icon("<i class='bi bi-code-slash'></i>")
                ->bg('bg-dark')
                ->tx('text-white')
                ->color('dark')
                ->creator(['admin'])
        )
        ->addPermission(
            Permission::make('api_internal_user', 'api')
                ->name('API Interno')
                ->icon("<i class='bi bi-arrow-through-heart'></i>")
                ->bg('bg-primary')
                ->tx('text-white')
                ->color('primary')
                ->creator(['admin'])
        )
        ->addPermission(
            Permission::make('api_public_access', 'api')
                ->name('Utente API')
                ->icon("<i class='bi bi-bug'></i>")
                ->bg('bg-info')
                ->tx('text-white')
                ->color('info')
                ->creator(['admin', 'administrator'])
        );

    Permissions::area('backend')
        ->route('home', 'backend.home')
        ->route('login', 'backend.account.login')
        ->route('password-restore', 'backend.account.password.restore')
        ->route('password-recovery', 'backend.account.password.recovery')
        ->route('password-set', 'backend.account.password.set');

    Permissions::area('api')
        ->route('home', 'backend.home')
        ->route('login', 'backend.account.login')
        ->route('password-restore', 'backend.account.password.restore')
        ->route('password-recovery', 'backend.account.password.recovery')
        ->route('password-set', 'backend.account.password.set')
        ->function('creation', 'apiUser')
        ->function('modify', 'apiUser')
        ->function('info', 'infoApiUser');

    Permissions::replace(\Wonder\App\Module\Registry::mergePermissions(Permissions::instance()));

    $customPermissionsFile = $ROOT."/custom/config/permissions.php";

    if (is_file($customPermissionsFile)) {
        require $customPermissionsFile;
    }

    $PERMITS = Permissions::toArray();
