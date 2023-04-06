<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Stile input";
    $INFO_PAGE->table = $TABLE->CSS_DROPDOWN;
    $INFO_PAGE->tableName = "css_dropdown";

    $SQL = sqlSelect($INFO_PAGE->tableName, ['id' => 1], 1);
    $VALUES = $SQL->row;

    if (isset($_POST['modify'])) {
        
        $VALUES = formToArray($INFO_PAGE->tableName, $_POST, $INFO_PAGE->table);
        
        if (empty($ALERT)) {
            sqlModify($INFO_PAGE->tableName, $VALUES, 'id', 1);
        }

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
                            <h6>Bordi</h6>
                        </div>
                        <div class="col-4">
                            <?=text('Spessore', 'border_width', 'required'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('Raggio', 'border_radius', 'required'); ?>
                        </div>
                    </wi-card>

                </div>
            </div>

            <div class="col-3">
                <div class="row g-3">

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Colore</h6>
                        </div>
                        <div class="col-12">
                            <?=color('Testo', 'tx', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=color('Sfondo', 'bg', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=color('Sfondo hover', 'bg_hover', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=color('Bordi', 'border_color', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=submit('Modifica dropdown', 'modify'); ?>
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