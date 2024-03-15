<!-- Open wrapper -->
<div class="wrapper">

    <!-- Sidebar Holder -->
    <nav id="sidebar">

        <div class="sidebar-navbar bg-body-secondary border-end d-flex flex-column flex-shrink-0">

            <ul class="list-unstyled components mb-auto sidebar-navbar-nav">

                <?php
                    
                    $url = $_SERVER['SCRIPT_NAME'];
                    $pathArray = explode("/", $url);

                    array_shift($pathArray);
                    array_shift($pathArray);

                    $offcanvasHTML = "";

                    foreach ($NAV_BACKEND as $navs) {

                        $titleNav = isset($navs['title']) ? $navs['title'] : 'ND';
                        $folderNav = isset($navs['folder']) ? $navs['folder'] : '';
                        $iconNav = isset($navs['icon']) ? $navs['icon'] : 'bi-bug';
                        $fileNav = isset($navs['file']) ? $navs['file'] : '';
                        $authNav = isset($navs['authority']) ? $navs['authority'] : [];
                        $subNav = isset($navs['subnavs']) ? $navs['subnavs'] : [];
                        $targetId = code(10, 'numbers', 'sidebar-');

                        if (!$authNav || count(array_intersect($authNav, $USER->authority)) >= 1) {

                            $activeNav = false;

                            if (in_array($folderNav, $pathArray)) { $activeNav = true; }
        
                            $offcanvas = [];
                            
                            if (!empty($subNav)) {

                                foreach ($subNav as $sub) {
                                    
                                    $titleSub = isset($sub['title']) ? $sub['title'] : 'ND';
                                    $folderSub = isset($sub['folder']) ? $sub['folder'] : '';
                                    $authSub = isset($sub['authority']) ? $sub['authority'] : [];
                                    $fileSub = isset($sub['file']) ? $sub['file'] : '';

                                    $activeSub = false;

                                    if (!$authSub || count(array_intersect($authSub, $USER->authority)) >= 1) {

                                        if ($folderNav == $folderSub) {
                                            if (in_array($folderNav, $pathArray) && in_array($fileSub, $pathArray)){
                                                $activeSub = true;
                                                $activeNav = true;
                                            }
                                        } else {
                                            if (in_array($folderSub, $pathArray)) {
                                                $activeSub = true;
                                                $activeNav = true;
                                            }
                                        }

                                        array_push($offcanvas, [
                                            'title' => $titleSub,
                                            'link' => $PATH->site.'/backend/'.$folderSub.'/'.$fileSub,
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
                                $subnavsToggle = "type='button' data-bs-toggle='offcanvas' data-bs-target='#$targetId' aria-label='Close'";
                            } else {
                                $subnavsToggle = "href='$PATH->site/backend/$folderNav/$fileNav'";
                            }
        
                            echo "
                            <li class='$navClass'>
                                <a $subnavsToggle class='text-body-emphasis'>       
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
                    
                <li>
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
                        'link' => $PATH->site.'/backend/account',
                        'active' => in_array('account', $pathArray) ? true : false
                    ],[
                        'title' => 'Esci',
                        'link' => $PATH->site.'/backend',
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
            