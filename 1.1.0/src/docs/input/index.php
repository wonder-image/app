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
        
        $SEO->title = "Demo input";
        $SEO->description = "";

        include $ROOT_APP.'/utility/frontend/head.php';
        
    ?>

    <link rel="stylesheet" href="<?=$PATH->appCss?>/docs/index.css">

</head>
<body>

    <?php include $ROOT_APP.'/utility/frontend/body-start.php' ?>
    <?php include $ROOT.'/docs/utility/header.php' ?>

    <div class="w-100">
        <form class="w-100 d-grid col-2 gap-5">
            <?=text("Text", 'text', '', 'required', false)?>
            <?=number("Number", 'number', '', 'required', false)?>
            <div class="col-2">
                <?=email("Email", 'email', '', 'required', false)?>
            </div>
            <div class="col-2">
                <?=password("Password", 'password', '', 'required', false)?>
            </div>
            <?=selectDate("Date", 'date', '21/03/2001', 'required',  date('d/m/Y', strtotime("-100 years")), date('d/m/Y', strtotime("-18 years")), false)?>
            <?=countryList('EU', 'Stato', 'state', '', '')?>
            <div class="col-2">
                <?=dateRange("Daterange", 'daterange', '', 'required', date('d/m/Y'))?>
            </div>
            <div class="col-2">
                <?=dateTimeRange("Datetimerange", 'datetimerange', '', 'required', date('d/m/Y'))?>
            </div>
            <div class="col-2">
            <?php

                $option = [
                    1 => 'Prova-1',
                    2 => 'Prova-2',
                    3 => 'Prova-3',
                    4 => 'Prova-4',
                    5 => 'Prova-5',
                ];

                echo select('Seleziona', 'select', $option, '', 'required');
                
            ?>
            </div>
            <div class="col-2">
                <?=textarea("Descrizione", 'description', '', '', false)?>
            </div>
            <?=checkbox('Checkbox', 'checkbox', $option, 'checkbox');?>
            <?=checkbox('Radio', 'radio', $option, 'radio');?>
            <div class="col-2">
                <?=checkbox('', 'checkbox', ["true" => ["label" => "Iscrivendoti alla newsletter, accetti la <a href='' target='_blank' rel='noopener noreferrer'>Politica sulla privacy</a> della nostra azienda.", "attribute" => "required"]], 'checkbox', '');?>
            </div>
            <div class="col-2">
                <?=submit('INVIA', 'upload', 'btn-success f-end')?>
            </div>
        </form>
    </div>

    <?php include $ROOT.'/docs/utility/footer.php' ?>
    <?php include $ROOT_APP.'/utility/frontend/body-end.php' ?>
    
</body>
</html>