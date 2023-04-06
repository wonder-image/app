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

</head>
<body>

    <?php include $ROOT_APP.'/utility/frontend/body-start.php' ?>

    <section>
        <div class="content content-little">
            <form class="w-100 d-grid col-2 gap-5">
                <?=textInput("Text", 'text', '', 'required', false)?>
                <?=numberInput("Number", 'number', '', 'required', false)?>
                <div class="col-2">
                    <?=emailInput("Email", 'email', '', 'required', false)?>
                </div>
                <div class="col-2">
                    <?=passwordInput("Password", 'password', '', 'required', false)?>
                </div>
                <?=dateInput("Date", 'date', '21/03/2001', 'required',  date('d/m/Y', strtotime("-100 years")), date('d/m/Y', strtotime("-18 years")), false)?>
                <?=countryInput('EU', 'Stato', 'state', '', '')?>
                <div class="col-2">
                    <?=dateRangeInput("Daterange", 'daterange', '', 'required', date('d/m/Y'))?>
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

                    echo selectInput('Seleziona', 'select', $option, '', 'required');
                    
                ?>
                </div>
                <div class="col-2">
                    <?=textareaInput("Descrizione", 'description', '', '', false)?>
                </div>
                <?=checkboxInput('Checkbox', 'checkbox', $option, 'checkbox');?>
                <?=checkboxInput('Radio', 'radio', $option, 'radio');?>
                <div class="col-2">
                    <?=checkboxInput('', 'checkbox', ["true" => ["label" => "Iscrivendoti alla newsletter, accetti la <a href='' target='_blank' rel='noopener noreferrer'>Politica sulla privacy</a> della nostra azienda.", "attribute" => "required"]], 'checkbox', '');?>
                </div>
                <div class="col-2">
                    <?=inputSubmit('INVIA', 'upload', 'btn-success f-end')?>
                </div>
            </form>
        </div>
    </section>

    <?php include $ROOT_APP.'/utility/frontend/body-end.php' ?>
    
</body>
</html>