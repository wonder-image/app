<!-- Open wrapper -->
<div class="wrapper">

    <!-- Sidebar Holder -->
    <nav id="sidebar" class="bg-dark">

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
                            <div class='line'></div>
                            <a $subnavsToggle>       
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

    <nav id="topbar" class="bg-light border-bottom">

        <a href='<?=$PATH->site?>/backend/account' type='button' class='btn btn-outline-dark btn-sm mr-1'>
            <i class='bi bi-person-fill'></i>
        </a>

        <a href="https://www.wonderimage.it" target="_blank" rel="noopener noreferrer" class="position-absolute top-50 start-50 translate-middle" style="height: 30px;">
            <img src="<?=$PATH->app?>/assets/logos/Wonder-Image.png" class="h-100" alt="">
        </a>
        
        <a href="<?=$PATH->site?>/backend" type="button" class="btn btn-outline-danger btn-sm float-end phone-none">
            Esci
        </a>

        <div id="menu" class="pc-none" onclick="menu()">
            <div class="bar bar-1 bg-dark"></div>
            <div class="bar bar-2 bg-dark"></div>
            <div class="bar bar-3 bg-dark"></div>
        </div>

    </nav>

    <!-- Open Page Content -->
    <div id="content">
        
        <?=modal()?>