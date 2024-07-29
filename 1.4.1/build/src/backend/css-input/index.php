<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Stile input";
    $INFO_PAGE->table = $TABLE->CSS_INPUT;
    $INFO_PAGE->tableName = "css_input";

    $SQL = sqlSelect($INFO_PAGE->tableName, ['id' => 1], 1);
    $VALUES = $SQL->row;

    if (isset($_POST['modify'])) {
        
        $VALUES = formToArray($INFO_PAGE->tableName, $_POST, $INFO_PAGE->table);
        
        if (empty($ALERT)) { sqlModify($INFO_PAGE->tableName, $VALUES, 'id', 1); }
        if (empty($ALERT)) { cssRoot(); }
        if (empty($ALERT)) { header("Location: ?alert=662"); }

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

                    <wi-card class="col-6">
                        <div class="col-12">
                            <h6>Bordi</h6>
                        </div>
                        <div class="col-6">
                            <?=color('Colore', 'border_color', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=color('Colore focus', 'border_color_focus', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Raggio', 'border_radius', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <h6>Spessore bordi</h6>
                        </div>
                        <div class="col-3">
                            <?=text('Alto', 'border_top', 'required'); ?>
                        </div>
                        <div class="col-3">
                            <?=text('Destra', 'border_right', 'required'); ?>
                        </div>
                        <div class="col-3">
                            <?=text('Basso', 'border_bottom', 'required'); ?>
                        </div>
                        <div class="col-3">
                            <?=text('Sinistra', 'border_left', 'required'); ?>
                        </div>
                    </wi-card>
                    
                    <wi-card class="col-6">
                        <div class="col-12">
                            <h6>Calendario</h6>
                        </div>
                        <div class="col-6">
                            <?=color('Data default', 'date_default', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=color('Data attiva', 'date_active', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=color('Data', 'date_bg', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=color('Data hover', 'date_bg_hover', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Raggio', 'date_border_radius', 'required'); ?>
                        </div>
                    </wi-card>
                    
                    <wi-card class="col-6">
                        <div class="col-12">
                            <h6>Label</h6>
                        </div>
                        <div class="col-6">
                            <?=color('Colore', 'label_color', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=color('Colore', 'label_color_focus', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Font weight', 'label_weight', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Font weight focus', 'label_weight_focus', 'required'); ?>
                        </div>
                    </wi-card>

                </div>
            </div>

            <div class="col-3">
                <div class="row g-3">

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Colori</h6>
                        </div>
                        <div class="col-6">
                            <?=color('Testo', 'tx_color', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=color('Sfondo', 'bg_color', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=color('Sfondo disabilitato', 'disabled_bg_color', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Input select</h6>
                        </div>
                        <div class="col-12">
                            <?=color('Hover', 'select_hover', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=submit('Modifica input', 'modify'); ?>
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