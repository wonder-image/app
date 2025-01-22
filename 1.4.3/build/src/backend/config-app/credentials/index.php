<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Credenziali";
    $INFO_PAGE->table = $TABLE->SECURITY;
    $INFO_PAGE->tableName = "security";

    $SQL = sqlSelect($INFO_PAGE->tableName, ['id' => 1], 1);
    $VALUES = $SQL->row;

    if (isset($_POST['modify'])) {
        
        $VALUES = formToArray($INFO_PAGE->tableName, $_POST, $INFO_PAGE->table);
        
        if (empty($ALERT)) { sqlModify($INFO_PAGE->tableName, $VALUES, 'id', 1); }
        if (empty($ALERT)) { header("Location: ?alert=653"); }

    }

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$INFO_PAGE->title?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>
    
    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <form action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
        <div class="row g-3">

            <wi-card class="col-12">
                <h3><?=$INFO_PAGE->title?></h3>
            </wi-card>

            <div class="col-9">
                <div class="row g-3">

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Wonder Image</h6>
                        </div>
                        <div class="col-12">
                            <?=text('Api Key', 'api_key', 'disabled'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Google Cloud Platform</h6>
                        </div>
                        <div class="col-6">
                            <?=text('ID Progetto', 'gcp_project_id', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Api Key', 'gcp_api_key', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-6">
                        <div class="col-12">
                            <h6>Google reCAPTCHA</h6>
                            <p>Per creare la chiave del sito <a href="https://www.google.com/recaptcha/admin/create?hl=it" target="_blank" rel="noopener noreferrer">clicca qui</a>.</p>
                        </div>
                        <div class="col-12">
                            <?=text('Chiave Sito', 'g_recaptcha_site_key'); ?>
                        </div> 
                    </wi-card>

                    <wi-card class="col-6">
                        <div class="col-12">
                            <h6>Google Place</h6>
                            <p>Per trovare il Place Id <a href="https://developers.google.com/maps/documentation/geocoding/overview?hl=it#how-the-geocoding-api-works" target="_blank" rel="noopener noreferrer">clicca qui</a>.</p>
                        </div>
                        <div class="col-12">
                            <?=text('Place ID', 'g_maps_place_id'); ?>
                        </div>
                    </wi-card>

                </div>
            </div>

            <div class="col-3">
                <div class="row g-3">

                    <wi-card class="col-12">
                        <div class="col-12">
                            <?=submit('Modifica', 'modify'); ?>
                        </div>
                    </wi-card>

                </div>
            </div>

        </div>
    </form>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>