<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";
    
?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>
    
    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <div class="row g-3">

        <wi-card class="col-3">

            <?php

                foreach ($NAV_BACKEND as $navs) {

                    $titleNav = isset($navs['title']) ? $navs['title'] : 'ND';
                    $folderNav = isset($navs['folder']) ? $navs['folder'] : 'home';
                    $iconNav = isset($navs['icon']) ? $navs['icon'] : 'bi-bug';
                    $fileNav = isset($navs['file']) ? $navs['file'] : '';
                    $authNav = isset($navs['authority']) ? $navs['authority'] : [];
                    $subNav = isset($navs['subnavs']) ? $navs['subnavs'] : [];

                    if ($titleNav != 'Home') {
                        if (!$authNav || count(array_intersect($authNav, $USER->authority)) >= 1) {

                            if (empty($subNav)) {

                                $subnavsList = '';

                            }else{
                                
                                $subNavs = "";

                                foreach ($subNav as $sub) {
                                    
                                    $titleSub = isset($sub['title']) ? $sub['title'] : 'ND';
                                    $folderSub = isset($sub['folder']) ? $sub['folder'] : 'home';
                                    $authSub = isset($sub['authority']) ? $sub['authority'] : [];
                                    $fileSub = isset($sub['file']) ? $sub['file'] : '';

                                    if (!$authSub || count(array_intersect($authSub, $USER->authority)) >= 1) {
                                        $subNavs .= "<a class='list-group-item list-group-item-action' href='$PATH->site/backend/$folderSub/$fileSub'>$titleSub <i class='bi bi-chevron-right float-end'></i></a>";
                                    }
                                    
                                }

                                $subnavsList = "$subNavs";

                            }

                            if (!empty($subnavsList)) {

                                echo "
                                <div class='list-group ps-2'>
                                    <li class='list-group-item list-group-item-dark'><i class='bi $iconNav'></i> $titleNav</li>
                                    $subnavsList
                                </div>";

                            }else{

                                echo "
                                <div class='list-group ps-2'>
                                    <a href='$PATH->site/backend/$folderNav/$fileNav' type='button' class='list-group-item list-group-item-dark list-group-item-action'><i class='bi $iconNav mr-2'></i> $titleNav <i class='bi bi-chevron-right float-end'></i></a>
                                </div>";

                            }

                        }
                    }

                }

            ?>
        </wi-card>

        <wi-card class="col-9">
            <span>
                Benvenuto,<br>
                in questo reparto potrai aggiornare o modificare il sito.<br>
                <br>
                Per ulteriori info o dubbi contattaci: <br>
                Cellulare: <a href="tel:393911220336" target="_blank" rel="noopener noreferrer">391 1220336</a> <br>
                Whatsapp:  <a href="https://wa.me/3911220336?text=Ciao%20Andrea%20ho%20bisogno%20di%20un%20aiuto" target="_blank" rel="noopener noreferrer">391 1220336</a> <br>
                Email:     <a href="mailto:marinoni@wonderimage.it" target="_blank" rel="noopener noreferrer">marinoni@wonderimage.it</a> <br>
                Andrea Marinoni
            </span>
        </wi-card>

    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>