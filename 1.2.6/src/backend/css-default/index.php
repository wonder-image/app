<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Impostazioni CSS";
    $INFO_PAGE->table = $TABLE->CSS_DEFAULT;
    $INFO_PAGE->tableName = "css_default";

    $SQL = sqlSelect($INFO_PAGE->tableName, ['id' => 1], 1);
    $VALUES = $SQL->row;

    if (isset($_POST['modify'])) {
        
        $VALUES = formToArray($INFO_PAGE->tableName, $_POST, $INFO_PAGE->table);
        
        if (empty($ALERT)) { sqlModify($INFO_PAGE->tableName, $VALUES, 'id', 1); }
        if (empty($ALERT)) { header("Location: ?alert=660"); }

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
                            <h6>Default Font</h6>
                        </div>
                        <div class="col-5">
                            <?php

                                $FONTS = [];

                                foreach (sqlSelect('css_font', ['visible' => 'true'])->row as $key => $row) { $FONTS[$row['id']] = $row['font_family']; }

                                echo select('Font family', 'font_id', $FONTS, null, 'required'); 

                            ?>
                        </div>
                        <div class="col-3">
                            <?=text('Font weight', 'font_weight', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Font size', 'font_size', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Line height', 'line_height', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Titolo grande</h6>
                        </div>
                        <div class="col-5">
                            <?=select('Font family', 'title_big_font_id', $FONTS, null, 'required');?>
                        </div>
                        <div class="col-3">
                            <?=text('Font weight', 'title_big_font_weight', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Font size', 'title_big_font_size', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Line height', 'title_big_line_height', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Titolo</h6>
                        </div>
                        <div class="col-5">
                            <?=select('Font family', 'title_font_id', $FONTS, null, 'required');?>
                        </div>
                        <div class="col-3">
                            <?=text('Font weight', 'title_font_weight', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Font size', 'title_font_size', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Line height', 'title_line_height', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Sottotitolo</h6>
                        </div>
                        <div class="col-5">
                            <?=select('Font family', 'subtitle_font_id', $FONTS, null, 'required');?>
                        </div>
                        <div class="col-3">
                            <?=text('Font weight', 'subtitle_font_weight', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Font size', 'subtitle_font_size', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Line height', 'subtitle_line_height', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Testo</h6>
                        </div>
                        <div class="col-5">
                            <?=select('Font family', 'text_font_id', $FONTS, null, 'required');?>
                        </div>
                        <div class="col-3">
                            <?=text('Font weight', 'text_font_weight', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Font size', 'text_font_size', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Line height', 'text_line_height', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Testo piccolo</h6>
                        </div>
                        <div class="col-5">
                            <?=select('Font family', 'text_small_font_id', $FONTS, null, 'required');?>
                        </div>
                        <div class="col-3">
                            <?=text('Font weight', 'text_small_font_weight', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Font size', 'text_small_font_size', 'required'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Line height', 'text_small_line_height', 'required'); ?>
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
                            <?=color('Testo', 'tx_color', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=color('Sfondo', 'bg_color', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Bottoni</h6>
                        </div>
                        <div class="col-6">
                            <?=text('Font size', 'button_font_size', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Line height', 'button_line_height', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Font weight', 'button_font_weight', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Raggio bordi', 'button_border_radius', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Spessore bordi', 'button_border_width', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Badge</h6>
                        </div>
                        <div class="col-6">
                            <?=text('Font size', 'badge_font_size', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Line height', 'badge_line_height', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Font weight', 'badge_font_weight', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Raggio bordi', 'badge_border_radius', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Spessore bordi', 'badge_border_width', 'required'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Altro</h6>
                        </div>
                        <div class="col-12">
                            <?=text('Spaziatore', 'spacer', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Altezza header', 'header_height', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=submit('Modifica CSS', 'modify'); ?>
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