<!-- Open wrapper -->
<div class="wrapper">

    <?php
        $navigation = \Wonder\Backend\Support\BackendNavigation::all();
        $currentFile = $PAGE->fileName;
        $currentDir = str_replace('backend/', '', $PAGE->dir);
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
        $matchesNavPath = static function (string $folder, string $file = '') use ($PATH, $requestPath): bool {
            $basePath = rtrim(parse_url((string) ($PATH->backend ?? '/backend'), PHP_URL_PATH) ?? '/backend', '/');
            $folder = trim($folder, '/');
            $file = trim($file, '/');
            $targetPath = $basePath.($folder !== '' ? '/'.$folder : '');

            if ($file !== '') {
                $targetPath .= '/'.$file;
            }

            $targetPath = rtrim($targetPath, '/');
            $request = rtrim($requestPath, '/');

            if ($targetPath === '') {
                return $request === '';
            }

            return $request === $targetPath || str_starts_with($request.'/', $targetPath.'/');
        };
        $sidebarItems = [];
        $offcanvasMarkup = [];

        foreach ($navigation as $navs) {
            $titleNav = (string) ($navs['title'] ?? 'ND');
            $folderNav = (string) ($navs['folder'] ?? '');
            $iconNav = (string) ($navs['icon'] ?? 'bi-bug');
            $fileNav = (string) ($navs['file'] ?? '');
            $authNav = $navs['authority'] ?? [];
            $subNav = $navs['subnavs'] ?? [];

            if ($authNav && count(array_intersect($authNav, $USER->authority)) < 1) {
                continue;
            }

            $activeNav = $currentDir === $folderNav || $matchesNavPath($folderNav, $fileNav);
            $targetId = code(10, 'numbers', 'sidebar-');
            $offcanvas = [];

            foreach ($subNav as $sub) {
                $titleSub = (string) ($sub['title'] ?? 'ND');
                $folderSub = (string) ($sub['folder'] ?? '');
                $authSub = $sub['authority'] ?? [];
                $fileSub = (string) ($sub['file'] ?? '');

                if ($authSub && count(array_intersect($authSub, $USER->authority)) < 1) {
                    continue;
                }

                $activeSub = false;

                if ($folderNav === $folderSub) {
                    if (($currentDir === $folderNav && $currentFile === $fileSub) || $matchesNavPath($folderSub, $fileSub)) {
                        $activeSub = true;
                        $activeNav = true;
                    }
                } elseif ($currentDir === $folderSub || $matchesNavPath($folderSub, $fileSub)) {
                    $activeSub = true;
                    $activeNav = true;
                }

                $offcanvas[] = [
                    'title' => $titleSub,
                    'link' => $PATH->backend.'/'.$folderSub.'/'.$fileSub,
                    'active' => $activeSub,
                ];
            }

            $hasOffcanvas = !empty($offcanvas);

            if ($hasOffcanvas) {
                $offcanvasMarkup[] = sidebarOffcanvas($targetId, $titleNav, $offcanvas);
                $url = null;
            } else {
                $urlParser = new \Wonder\Http\UrlParser($fileNav);
                $url = $urlParser->isAbsolute() ? $fileNav : $PATH->backend.'/'.$folderNav.'/'.$fileNav;
            }

            $sidebarItems[] = [
                'active' => $activeNav,
                'icon' => $iconNav.($activeNav ? '-fill' : ''),
                'title' => $titleNav,
                'target_id' => $targetId,
                'url' => $url,
                'has_offcanvas' => $hasOffcanvas,
            ];
        }

        $settingsTargetId = code(10, 'numbers', 'sidebar-');
        $settingsOffcanvas = sidebarOffcanvas($settingsTargetId, 'Impostazioni', [
            [
                'title' => 'Profilo',
                'link' => $PATH->backend.'/account',
                'active' => $currentDir === 'account',
            ],
            [
                'title' => 'Esci',
                'link' => $PATH->backend.'/account/logout/',
                'active' => false,
            ],
        ]);
    ?>

    <!-- Sidebar Holder -->
    <nav id="sidebar">

        <div class="sidebar-navbar bg-body-secondary border-end d-flex flex-column flex-shrink-0">

            <ul class="list-unstyled components mb-auto sidebar-navbar-nav">
                <?php foreach ($sidebarItems as $item) { ?>
                <li class="<?=e($item['active'] ? 'active' : '')?>">
                    <?php if ($item['has_offcanvas']) { ?>
                    <a
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#<?=e($item['target_id'])?>"
                        aria-label="Close"
                        class="text-body-emphasis"
                    >
                        <i class="bi <?=e($item['icon'])?>"></i>
                        <span><?=e($item['title'])?></span>
                    </a>
                    <?php } else { ?>
                    <a href="<?=e($item['url'])?>" class="text-body-emphasis">
                        <i class="bi <?=e($item['icon'])?>"></i>
                        <span><?=e($item['title'])?></span>
                    </a>
                    <?php } ?>
                </li>
                <?php } ?>
            </ul>

            <ul class="list-unstyled components border-top mb-0">
                    
                <li class="<?=e(($currentDir == 'account') ? 'active' : '')?>">
                    <div class="line bg-body"></div>
                    <a class="text-body-emphasis" type="button" data-bs-toggle="offcanvas" data-bs-target="#<?=e($settingsTargetId)?>">       
                        <i class="bi bi-gear-wide-connected"></i>
                        <span>Impostazioni</span>
                    </a>
                </li>

            </ul>

        </div>

        <div class="sidebar-offcanvas">
            <?=implode('', $offcanvasMarkup)?>
            <?=$settingsOffcanvas?>
        </div>

    </nav>

    <nav id="topbar" class="bg-body border-bottom">

        <a href="https://www.wonderimage.it" target="_blank" rel="noopener noreferrer" class="" style="height: 30px;">
            <img id="be-logo-black" src="<?=e($DEFAULT->BeLogoBlack ?? '')?>" class="h-100 d-none"><img id="be-logo-white" src="<?=e($DEFAULT->BeLogoWhite ?? '')?>" class="h-100 d-none">
        </a>

        <button id="menu" class="btn btn-outline-dark btn-sm pc-none ms-2 float-end" type="button" onclick="menu();">
            <i class="open-menu bi bi-list"></i><i class="close-menu bi bi-x-lg d-none"></i>
        </button>

        <div class="dropdown float-end">
            <button id="bs-theme" class="btn btn-outline-dark btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-sun-fill light-theme"></i> <i class="bi bi-moon-stars-fill dark-theme"></i>
            </button>
            <ul class="dropdown-menu">
                <li><button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" onclick="bootstrapTheme(this.getAttribute('data-bs-theme-value'))"> <i class="bi bi-sun-fill me-2 opacity-75"></i> Light </button></li>
                <li><button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" onclick="bootstrapTheme(this.getAttribute('data-bs-theme-value'))"> <i class="bi bi-moon-stars-fill me-2 opacity-75"></i> Dark </button></li>
            </ul>
        </div>

    </nav>

    <script> bootstrapTheme(localStorage.theme); </script>

    <!-- Open Page Content -->
    <div id="content">

        <?=modal()?>

        <div class="w-100" style="min-height: calc(100vh - (50px + 22.5px + 1rem + 20px));">
                
            <div id="page-loading" class="position-absolute top-50 start-50 translate-middle text-center">
                <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status"></div>
            </div>
            
