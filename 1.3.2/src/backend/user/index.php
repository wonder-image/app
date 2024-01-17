<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";
    
    if (!empty($_GET['modify'])) {

        $TITLE = "Modifica $TEXT->titleS";

        $SQL = sqlSelect($NAME->table, ['id' => $_GET['modify']], 1);
        $VALUES = $SQL->row;

    } else {

        $TITLE = "Aggiungi $TEXT->titleS";

    }

    if (!empty($PAGE->redirect)) {
        $REDIRECT = $PAGE->redirect;
    } else {
        $REDIRECT = "$PATH->backend/$NAME->folder/list.php";
    }

    if (isset($_POST['upload'])) {

        if (isset($_GET['modify'])) {
            $MODIFY_ID = $_GET['modify'];
        } else if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
            $MODIFY_ID = $_POST['user_id'];
        } else {
            $MODIFY_ID = null;
        }

        $POST = array_merge($_POST, $_FILES);
        $UPLOAD = user($POST, $MODIFY_ID); 
        $VALUES = $UPLOAD->values;

        if (empty($ALERT)) {
            
            if (empty($_GET['modify'])) {

                $authority = permissions($_POST['authority'])->name;

                $content = "
                Ciao ".$_POST['name'].", benvenuto/a nello staff. <br>
                <br>
                Queste sono le tue credenziali: <br>
                Link accesso: <a href='$PATH->backend'>Clicca qui</a> <br>
                Username: <b>".$_POST['username']."</b> <br>
                Password: <b>".$_POST['password']."</b> <br>
                <br>
                Autorizzazione: <b>$authority</b>
                ";

                sendMail("noreply@wonderimage.it", $_POST['email'], "Benvenuto nello staff", $content);
        
            }

        }

        if (empty($ALERT)) {
            if (isset($_POST['upload-add'])) {
                header("Location: $PATH->backend/$NAME->folder/index.php?redirect=$PAGE->redirectBase64");
            } else {
                header("Location: $REDIRECT");
            }
        }

    }

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$TITLE?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>
    
    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <form action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">

        <input type="hidden" name="area" value="backend">
        <input type="hidden" name="password" value="<?=code(2, 'letters').'-'.code(4, 'numbers');?>">

        <div class="row g-3">

            <wi-card class="col-12">
                <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$TITLE?></h3>
            </wi-card>

            <div class="col-9">
                <div class="row g-3">

                    <?php if (empty($_GET['modify'])) { ?>
                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Crea utente da email già esistente</h6>
                        </div>
                        <div class="col-6">
                            <?php

                                $OPTION = [
                                    '' => 'No'
                                ];

                                foreach (sqlSelect('user', "`area` NOT LIKE '%backend%' and `deleted` = 'false'")->row as $key => $value) {

                                    $id = $value['id'];
                                    $username = $value['username'];

                                    $OPTION[$id] = $username;

                                }

                                echo check('Username', 'user_id', $OPTION, "onclick=\"if (this.value != '') { disableInput('user'); } else { enabledInput('user'); }\"", 'radio', true); 
                                
                            ?>
                        </div>
                    </wi-card>
                    <?php } ?>
                    
                    <wi-card class="col-12">
                        <div class="col-4">
                            <?=text('Nome', 'name', 'required'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('Cognome', 'surname', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Username', 'username', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=email('Email', 'email', 'required'); ?>
                        </div>
                    </wi-card>

                </div>
            </div>

            <div class="col-3">
                <div class="row g-3">
                    
                    <wi-card>
                        <div class="col-12">
                            <?=inputFileDragDrop('Foto profilo', 'profile_picture', 'profile', 'image')?>
                        </div>
                        <div class="col-12">
                            <?php

                                $option = [ '' => '--' ];

                                foreach ($DEFAULT->colorUser as $key => $color) {
                                    if ($color['active']) {
                                        $option[$key] = $color['name'];
                                    }
                                }

                                echo select('Colore', 'color', $option);

                            ?>
                        </div>
                    </wi-card>

                    <wi-card>
                        <div class="col-12">
                            <?php

                                $A = [];
                                $authority = null;

                                foreach (permissionsBackend() as $key => $value) {
                                    if (count(array_intersect(permissions($key)->creator, $USER->authority)) >= 1) {
                                        $A[$key] = $value;
                                        if (isset($VALUES['authority'])) { 
                                            if (is_array(json_decode($VALUES['authority'], true)) && in_array($key, json_decode($VALUES['authority'], true))) {
                                                $authority = $key; 
                                            } elseif ($key == $VALUES['authority']) {
                                                $authority = $key; 
                                            }
                                        }
                                    }
                                }

                                echo select('Autorizzazione', 'authority', $A, 'old', 'required', $authority);
                                
                            ?>
                        </div>
                        <div class="col-12">
                            <?php

                                $checkbox = [
                                    'true' => "Abilitato",
                                    'false' => "Disabilitato",
                                ];

                                echo select('Stato', 'active', $checkbox, 'old', 'required');

                            ?>
                        </div>
                        <div class="col-12"> 
                            <?=submitAdd()?>
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