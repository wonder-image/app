<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";
    
    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Account";
    $INFO_PAGE->table = "user";

    $NAME = (object) array();
    $NAME->table = "user";
    $NAME->folder = "user";

    $SQL = sqlSelect($INFO_PAGE->table, ['id' => $USER->id], 1);
    $VALUES = $SQL->row;

    if (isset($_POST['modify'])) {
        
        $password = sanitize($_POST['password']);

        if (checkPassword($password, $VALUES['password'])) {
            
            $POST = array_merge($_POST, $_FILES);
            $UPLOAD = user($POST, $USER->id);
            $VALUES = $UPLOAD->values; 
            if (empty($ALERT)) { $ALERT = 604; }

        } else {

            $ALERT = 905;
                
        }

    }

    if (isset($_POST['modify-password'])) {
        
        $oldPassword = sanitize($_POST['old-password']);

        if (checkPassword($oldPassword, $VALUES['password'])) {

            $newPassword = hashPassword($_POST['new-password']);
            sqlModify($INFO_PAGE->table, ['password' => $newPassword], 'id', $USER->id);
            if (empty($ALERT)) { $ALERT = 603; }

        } else {

            $ALERT = 905;
                
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

    <div class="row g-3">

        <wi-card class="col-12">
            <h3>Impostazioni account</h3>
        </wi-card>

        <form class="col-9" action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
            <wi-card class="col-12">
                <div class="col-12">
                    <h6>Modifica dati</h6>
                </div>
                <div class="col-3">
                    <div class="row g-3">

                        <div class="col-12">
                            <?=inputFileDragDrop('', 'profile_picture', 'profile', 'image')?>
                        </div>

                    </div>
                </div>
                <div class="col-9">
                    <div class="row g-3">
                        <div class="col-5">
                            <?=text('Nome', 'name', 'required'); ?>
                        </div>
                        <div class="col-5">
                            <?=text('Cognome', 'surname', 'required'); ?>
                        </div>
                        <div class="col-8">
                            <?=text('Username', 'username', 'required'); ?>
                        </div>
                        <div class="col-4">
                            <?php

                                $option = [];

                                foreach ($DEFAULT->colorUser as $key => $color) {
                                    if ($color['active']) {
                                        $option[$key] = $color['name'];
                                    }
                                }

                                echo select('Colore', 'color', $option);

                            ?>
                        </div>
                        <div class="col-6">
                            <?=email('Email', 'email', 'required'); ?>
                        </div>
                        <div class="col-6">
                            <?=password('Password', 'password', 'required', ''); ?>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <?=submit('Modifica dati', 'modify'); ?>
                </div>
            </wi-card>
        </form>


        <form class="col-3" action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
            <wi-card class="col-12">
                <div class="col-12">
                    <h6>Modifica password</h6>
                </div>
                <div class="col-12">
                    <?=password('Vecchia password', 'old-password', 'required', '')?>
                </div>
                <div class="col-12">
                    <?=password('Nuova password', 'new-password', 'required', '')?>
                </div>
                <div class="col-12">
                    <?=submit('Modifica password', 'modify-password'); ?>
                </div>
            </wi-card>
        </form>

    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>


</body>
</html>