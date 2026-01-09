<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Loghi";
    $INFO_PAGE->table = $TABLE->LOGOS;
    $INFO_PAGE->tableName = "logos";

    $NAME = (object) array();
    $NAME->table = $INFO_PAGE->tableName;
    $NAME->folder = $INFO_PAGE->tableName;

    $PAGE_TABLE = $INFO_PAGE->table;

    $SQL = sqlSelect($NAME->table, ['id' => 1], 1);
    $VALUES = $SQL->row;

    if (isset($_POST['modify'])) {

        $_POST['slug'] = $SOCIETY->name;

        $POST = array_merge($_POST, $_FILES);
        
        $VALUES = formToArray($INFO_PAGE->tableName, $POST, $INFO_PAGE->table, $VALUES);
        
        if (empty($ALERT)) {sqlModify($INFO_PAGE->tableName, $VALUES, 'id', 1); }
        if (empty($ALERT)) { header("Location: ?alert=661"); }

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

            <div class="col-12">
                <div class="row g-3">

                    <wi-card class="col-8">
                        <div class="col-4">
                            <?=inputFileDragDrop('Logo', 'main', 'classic', 'png'); ?>
                        </div>
                        <div class="col-4">
                            <?=inputFileDragDrop('Logo nero', 'black', 'classic', 'png'); ?>
                        </div>
                        <div class="col-4">
                            <?=inputFileDragDrop('Logo bianco', 'white', 'classic', 'png'); ?>
                        </div>
                        <div class="col-12"></div>
                        <div class="col-4">
                            <?=inputFileDragDrop('Icona', 'icon', 'classic', 'png'); ?>
                        </div>
                        <div class="col-4">
                            <?=inputFileDragDrop('Icona Nera', 'icon_black', 'classic', 'png'); ?>
                        </div>
                        <div class="col-4">
                            <?=inputFileDragDrop('Icona Bianca', 'icon_white', 'classic', 'png'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-4">
                        <div class="col-12">
                            <?=inputFileDragDrop('Icona app', 'app_icon', 'classic', 'png'); ?>
                        </div>
                        <div class="col-12">
                            <?=submit('Modifica loghi', 'modify'); ?>
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