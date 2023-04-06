<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <?php 
        
        $SEO->title = "Demo button";
        $SEO->description = "";

        include $ROOT_APP.'/utility/frontend/head.php';
        
    ?>

    <link rel="stylesheet" href="<?=$PATH->appCss?>/docs/index.css">

</head>
<body>

    <?php include $ROOT_APP.'/utility/frontend/body-start.php' ?>

    <section id="modal-test" class="wi-modal no-interaction">

        <div class="bg wi-close-modal"></div>

        <div class="content wi-modal-content">
            <div class="wi-modal-header">
                <div class="wi-modal-title subtitle">
                    Modal title
                </div>
                <div class="wi-modal-close wi-close-modal">
                    <i class="bi bi-x-lg"></i>
                </div>
            </div>
            <div class="wi-modal-body no-scrollbar text">
                Modal description
            </div>
            <div class="wi-modal-footer">
                <div class="btn-group j-content-end">
                    <a class="btn btn-danger wi-close-modal">
                        CHIUDI
                    </a>
                    <div class="submit btn btn-success">
                        CONCLUDI
                    </div>
                </div>
            </div>
        </div>

    </section>

    <section id="modal-form" class="wi-modal no-interaction">

        <div class="bg wi-close-modal"></div>

        <div class="content wi-modal-content">
            <div class="wi-modal-header">
                <div class="wi-modal-title subtitle">
                    Modal form title
                </div>
                <div class="wi-modal-close wi-close-modal">
                    <i class="bi bi-x-lg"></i>
                </div>
            </div>
            <form action="" class="wi-modal-form" method="post" enctype="multipart/form-data">
                <div class="wi-modal-body no-scrollbar text">
                    <div class="w-100 d-grid col-1 gap-5">
                        <?=textInput("Testo", 'text', '')?>
                        <?=numberInput("Numero", 'date', '')?>
                        <?=textareaInput("Numero", 'date', '')?>
                        <?=textareaInput("Numero", 'date', '')?>
                        <?=textareaInput("Numero", 'date', '')?>
                        <?=textareaInput("Numero", 'date', '')?>
                        <?=textareaInput("Numero", 'date', '')?>
                        <?=textareaInput("Numero", 'date', '')?>
                        <?=textareaInput("Numero", 'date', '')?>
                    </div>
                </div>
                <div class="wi-modal-footer">
                    <div class="btn-group j-content-end">
                        <a class="btn btn-danger wi-close-modal">
                            CHIUDI
                        </a>
                        <button type="submit" class="btn btn-success" disabled>
                            INVIA FORM
                        </button>
                    </div>
                </div>
            </form>
        </div>

    </section>

    <?php include $ROOT.'/docs/utility/header.php' ?>

    <div class="w-100">
        <div class="title mb-4">
            Button
        </div>
        <div class="btn-group">
            <div class="btn btn-primary">
                .btn.btn-primary
            </div>
            <div class="btn btn-primary-o">
                .btn.btn-primary-o
            </div>
            <div class="btn btn-secondary">
                .btn.btn-secondary
            </div>
            <div class="btn btn-secondary-o">
                .btn.btn-secondary-o
            </div>
            <div class="btn btn-success">
                .btn.btn-success
            </div>
            <div class="btn btn-success-o">
                .btn.btn-success-o
            </div>
            <div class="btn btn-danger">
                .btn.btn-danger
            </div>
            <div class="btn btn-danger-o">
                .btn.btn-danger-o
            </div>
            <div class="btn btn-info">
                .btn.btn-info
            </div>
            <div class="btn btn-info-o">
                .btn.btn-info-o
            </div>
            <div class="btn btn-light">
                .btn.btn-light
            </div>
            <div class="btn btn-light-o">
                .btn.btn-light-o
            </div>
            <div class="btn btn-dark">
                .btn.btn-dark
            </div>
            <div class="btn btn-dark-o">
                .btn.btn-dark-o
            </div>
            <div class="btn btn-white">
                .btn.btn-white
            </div>
            <div class="btn btn-white-o">
                .btn.btn-white-o
            </div>
            <div class="btn btn-black">
                .btn.btn-black
            </div>
            <div class="btn btn-black-o">
                .btn.btn-black-o
            </div>
        </div>
    </div>
    
    <div class="w-100">
        <div class="title mb-4">
            Button icon
        </div>
        <div class="btn-group">
            <div class="btn btn-icon-right btn-primary">
                .btn.btn-icon-right.btn-primary <i class="bi bi-mailbox"></i>
            </div>
            <div class="btn btn-icon-left  btn-primary-o">
                <i class="bi bi-envelope"></i> .btn.btn-icon-left.btn-primary-o
            </div>
            <div class="btn btn-arrow btn-secondary">
                .btn.btn-arrow.btn-secondary <i class="bi bi-chevron-right"></i>
            </div>
        </div>
    </div>

    <div class="w-100">
        <div class="title mb-4">
            Button dropdown
        </div>
        
        <div class="w-50">
            <div class="wi-dropdown-btn">
                <div class="btn btn-icon-right btn-primary wi-switcher">
                    Dropdown start <i class="bi bi-chevron-down"></i>
                </div>
                <div class="wi-dropdown-list start">
                    <a href="download/csv.php" class="wi-dropdown-item">Scarica CSV</a>
                    <a href="download/xls.php" class="wi-dropdown-item">Scarica EXCEL</a>
                </div>
            </div>
        </div>

        <div class="w-50">
            <div class="wi-dropdown-btn f-end">
                <div class="btn btn-icon-right btn-primary wi-switcher">
                    Dropdown end <i class="bi bi-chevron-down"></i>
                </div>
                <div class="wi-dropdown-list end">
                    <a href="download/csv.php" class="wi-dropdown-item">Scarica CSV</a>
                    <a href="download/xls.php" class="wi-dropdown-item">Scarica EXCEL</a>
                </div>
            </div>
        </div>

        <div class="wi-dropdown-box mt-4">
            <div class="wi-dropdown-title wi-switcher fw-400">
                ACCOUNT CLIENTE <i class="bi bi-plus"></i>
            </div>   
            <div class="wi-dropdown-content">
                <div class="w-100">
                    Prova testo
                </div>
                <div class="w-100">
                    Prova testo
                </div>
                <div class="w-100">
                    Prova testo
                </div>
                <div class="w-100">
                    Prova testo
                </div>
            </div>
        </div>

    </div>

    <div class="w-100">
        <div class="title mb-4">
            Button modal
        </div>
        <div class="btn-group">
            <div class="btn btn-icon-left btn-primary" onclick="modal('#modal-test')">
                <i class="bi bi-view-list"></i> Modal
            </div>
            <div class="btn btn-icon-left btn-primary-o" onclick="modal('#modal-form')">
                <i class="bi bi-view-list"></i> Modal form
            </div>
        </div>
    </div>

    <div class="w-100">
        <div class="title mb-4">
            Badge
        </div>
        <div class="badge-group">
            <div class="badge badge-primary">
                .badge.badge-primary
            </div>
            <div class="badge badge-primary-o">
                .badge.badge-primary-o
            </div>
            <div class="badge badge-secondary">
                .badge.badge-secondary
            </div>
            <div class="badge badge-secondary-o">
                .badge.badge-secondary-o
            </div>
            <div class="badge badge-success">
                .badge.badge-success
            </div>
            <div class="badge badge-success-o">
                .badge.badge-success-o
            </div>
            <div class="badge badge-danger">
                .badge.badge-danger
            </div>
            <div class="badge badge-danger-o">
                .badge.badge-danger-o
            </div>
            <div class="badge badge-info">
                .badge.badge-info
            </div>
            <div class="badge badge-info-o">
                .badge.badge-info-o
            </div>
            <div class="badge badge-light">
                .badge.badge-light
            </div>
            <div class="badge badge-light-o">
                .badge.badge-light-o
            </div>
            <div class="badge badge-dark">
                .badge.badge-dark
            </div>
            <div class="badge badge-dark-o">
                .badge.badge-dark-o
            </div>
            <div class="badge badge-white">
                .badge.badge-white
            </div>
            <div class="badge badge-white-o">
                .badge.badge-white-o
            </div>
            <div class="badge badge-black">
                .badge.badge-black
            </div>
            <div class="badge badge-black-o">
                .badge.badge-black-o
            </div>
        </div>
    </div>

    <?php include $ROOT.'/docs/utility/footer.php' ?>
    <?php include $ROOT_APP.'/utility/frontend/body-end.php' ?>
    
</body>
</html>