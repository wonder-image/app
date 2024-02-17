<!-- Open wrapper -->
<div class="wrapper">

    <!-- Sidebar Holder -->
    <nav id="sidebar" class="bg-body-secondary border-end">

        <ul class="list-unstyled components">

            <?php
                
                $url = $_SERVER['SCRIPT_NAME'];
                $pathArray = explode("/", $url);

                array_shift($pathArray);
                array_shift($pathArray);

                foreach ($NAV_BACKEND as $navs) {

                    $titleNav = isset($navs['title']) ? $navs['title'] : 'ND';
                    $folderNav = isset($navs['folder']) ? $navs['folder'] : '';
                    $iconNav = isset($navs['icon']) ? $navs['icon'] : 'bi-bug';
                    $fileNav = isset($navs['file']) ? $navs['file'] : '';
                    $authNav = isset($navs['authority']) ? $navs['authority'] : [];
                    $subNav = isset($navs['subnavs']) ? $navs['subnavs'] : [];

                    if (!$authNav || count(array_intersect($authNav, $USER->authority)) >= 1) {

                        if (in_array($folderNav, $pathArray)) {
                            $activeNav = "active";
                            $activeSubs = "show";
                            $activeSub = "";
                            $fillImg = "-fill";
                        }else{
                            $activeNav = "";
                            $activeSubs = "";
                            $activeSub = "";
                            $fillImg = "";
                        }
    
                        if (empty($subNav)) {
                            $subnavsNav = '';
                            $subNavs = "";
                            $subnavsList = '';
                        }else{
    
                            $subnavsNav = "data-bs-toggle='collapse' data-bs-target='#$folderNav' aria-expanded='false'";
                            $subNavs = "";
                            $activeSub = "";
                            
                            foreach ($subNav as $sub) {
                                
                                $titleSub = isset($sub['title']) ? $sub['title'] : 'ND';
                                $folderSub = isset($sub['folder']) ? $sub['folder'] : '';
                                $authSub = isset($sub['authority']) ? $sub['authority'] : [];
                                $fileSub = isset($sub['file']) ? $sub['file'] : '';

                                if (!$authSub || count(array_intersect($authSub, $USER->authority)) >= 1) {
                                    if ($folderNav == $folderSub) {
                                        if(in_array($folderNav, $pathArray) && in_array($fileSub, $pathArray)){
                                            $activeSub = "active";
                                            $activeSubs = 'show';
                                        }else{
                                            $activeSub = "";
                                        }
                                    }else{
                                        if (in_array($folderSub, $pathArray)) {
                                            $activeSub = "active";
                                            $activeSubs = 'show';
                                        }else{
                                            $activeSub = "";
                                        }
                                    }
        
                                    $subNavs .= "
                                    <li class='$activeSub'>
                                        <a href='$PATH->site/backend/$folderSub/$fileSub'>       
                                            <span>$titleSub</span>
                                        </a>
                                    </li>
                                    ";
                                }
                                
                            }
    
                            $subnavsList = "
                            <ul class='collapse $activeSubs list-unstyled' id='$folderNav'>
                                $subNavs
                            </ul>";
    
                        }
    
                        if ($activeSubs == 'show') {
                            $activeNav = 'active';
                            $fillImg = '-fill';
                        }

                        if (!empty($subnavsList)) {
                            $subnavsToggle = "href='#' type='button' data-bs-toggle='collapse' data-bs-target='#$folderNav' aria-expanded='false' aria-controls='$folderNav'";
                        }else{
                            $subnavsToggle = "href='$PATH->site/backend/$folderNav/$fileNav'";
                        }
    
                        echo "
                        <li class='$activeNav'>
                            <div class='line bg-body'></div>
                            <a $subnavsToggle class='text-body-emphasis'>       
                                <i class='bi $iconNav$fillImg'></i>
                                <span>$titleNav</span>
                            </a>
                            $subnavsList
                        </li>
                        ";

                    }

                }

            ?>
            
        </ul>

    </nav>

    <nav id="topbar" class="bg-body border-bottom">

        <div class="dropdown float-start">
            <button class="btn btn-outline-dark btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-gear-wide-connected"></i>
            </button>
            <ul class="dropdown-menu">
                <li><a href="<?=$PATH->site?>/backend/account" class="dropdown-item" type="button"><i class="bi bi-person-fill me-2"></i> Profilo</a></li>
                <div class="dropdown-divider"></div>
                <li><a href="<?=$PATH->site?>/backend" class="dropdown-item text-danger" type="button"><i class="bi bi-box-arrow-left me-2"></i> Esci</a></li>
            </ul>
        </div>

        <a href="https://www.wonderimage.it" target="_blank" rel="noopener noreferrer" class="position-absolute top-50 start-50 translate-middle" style="height: 30px;">
            <img id="be-logo-black" src="<?=$DEFAULT->BeLogoBlack?>" class="h-100 d-none"><img id="be-logo-white" src="<?=$DEFAULT->BeLogoWhite?>" class="h-100 d-none">
        </a>

        <button id="menu" class="btn btn-outline-dark btn-sm pc-none ms-2 float-end" type="button" onclick="menu()">
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
            