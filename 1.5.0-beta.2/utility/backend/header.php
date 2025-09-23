<!-- Open wrapper -->
<div class="wrapper">

    <!-- Sidebar Holder -->
    <nav id="sidebar">

        <div class="sidebar-navbar bg-body-secondary border-end d-flex flex-column flex-shrink-0">

            <ul class="list-unstyled components mb-auto sidebar-navbar-nav">

                <?php
                    
                    $parsedUrl = parse_url($_SERVER["REQUEST_URI"])["path"];

                    $currentFile = $PAGE->fileName;
                    $currentDir = str_replace('backend/', '',$PAGE->dir);

                    $offcanvasHTML = "";

                    foreach ($NAV_BACKEND as $navs) {

                        $titleNav = $navs['title'] ?? 'ND';
                        $folderNav = $navs['folder'] ?? '';
                        $iconNav = $navs['icon'] ?? 'bi-bug';
                        $fileNav = $navs['file'] ?? '';
                        $authNav = $navs['authority'] ?? [];
                        $subNav = $navs['subnavs'] ?? [];
                        $targetId = code(10, 'numbers', 'sidebar-');

                        if (!$authNav || count(array_intersect($authNav, $USER->authority)) >= 1) {

                            $activeNav = false;

                            if ($currentDir == $folderNav) { $activeNav = true; }
        
                            $offcanvas = [];
                            
                            if (!empty($subNav)) {

                                foreach ($subNav as $sub) {
                                    
                                    $titleSub = $sub['title'] ?? 'ND';
                                    $folderSub = $sub['folder'] ?? '';
                                    $authSub = $sub['authority'] ?? [];
                                    $fileSub = $sub['file'] ?? '';

                                    $activeSub = false;

                                    if (!$authSub || count(array_intersect($authSub, $USER->authority)) >= 1) {

                                        if ($folderNav == $folderSub) {
                                            if ($currentDir == $folderNav && $currentFile == $fileSub){
                                                $activeSub = true;
                                                $activeNav = true;
                                            }
                                        } else {
                                            if ($currentDir == $folderSub) {
                                                $activeSub = true;
                                                $activeNav = true;
                                            }
                                        }

                                        array_push($offcanvas, [
                                            'title' => $titleSub,
                                            'link' => $PATH->backend.'/'.$folderSub.'/'.$fileSub,
                                            'active' => $activeSub
                                        ]);

                                    }
                                    
                                }
        
                                $offcanvasHTML .= sidebarOffcanvas(
                                    $targetId,
                                    $titleNav,
                                    $offcanvas
                                );
        
                            }

                            $navClass = $activeNav ? 'active' : '';
                            $fillImg = $activeNav ? '-fill' : '';

                            if (!empty($offcanvas)) {
                                $navAction = "type='button' data-bs-toggle='offcanvas' data-bs-target='#$targetId' aria-label='Close'";
                            } else {
                                $navUrl = isset(parse_url($fileNav)['host']) ? $fileNav : $PATH->backend.'/'.$folderNav.'/'.$fileNav ;
                                $navAction = "href='$navUrl'";
                            }
        
                            echo "
                            <li class='$navClass'>
                                <a $navAction class='text-body-emphasis'>       
                                    <i class='bi $iconNav$fillImg'></i> <span>$titleNav</span>
                                </a>
                            </li>";

                        }

                    }

                ?>
                
            </ul>

            <?php

                $impostazioniId = code(10, 'numbers', 'sidebar-');
 
            ?>

            <ul class="list-unstyled components border-top mb-0">
                    
                <li class="<?=($currentDir == 'account') ? 'active' : ''?>">
                    <div class="line bg-body"></div>
                    <a class="text-body-emphasis" type="button" data-bs-toggle="offcanvas" data-bs-target="#<?=$impostazioniId?>">       
                        <i class="bi bi-gear-wide-connected"></i>
                        <span>Impostazioni</span>
                    </a>
                </li>

            </ul>

        </div>

        <div class="sidebar-offcanvas">
            <?php

                echo $offcanvasHTML;
                
                echo sidebarOffcanvas($impostazioniId, "Impostazioni", [
                    [
                        'title' => 'Profilo',
                        'link' => $PATH->backend.'/account',
                        'active' => ($currentDir == 'account') ? true : false
                    ],[
                        'title' => 'Esci',
                        'link' => $PATH->backend.'/',
                        'active' => false
                    ]
                ]);

            ?>
        </div>

    </nav>

    <nav id="topbar" class="bg-body border-bottom">

        <a href="https://www.wonderimage.it" target="_blank" rel="noopener noreferrer" class="" style="height: 30px;">
            <img id="be-logo-black" src="<?=$DEFAULT->BeLogoBlack?>" class="h-100 d-none"><img id="be-logo-white" src="<?=$DEFAULT->BeLogoWhite?>" class="h-100 d-none">
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
            