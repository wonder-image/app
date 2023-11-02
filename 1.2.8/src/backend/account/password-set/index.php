<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $restriction = $_GET['r'];

    if (isset($_GET['r'])) {

        $restriction = $_GET['r'];

    } else {

        header("Location: ../login/?alert=913");
        exit;

    }

    $USER_ID = json_decode(base64_decode($_GET['r']))->user_id;
    
    if (isset($_POST['set-password'])) {

        $VALUES = $_POST;

        $password = hashPassword($_POST['password']);

        if (empty($ALERT)) {

            $VERIFY = verifyUser('id', $USER_ID, 'backend');

            if ($VERIFY->response) {
                
                $USER = $VERIFY->user;

                if (empty($USER->password)) {
                    
                    sqlModify('user', [ 'password' => $password ], 'id', $USER_ID);

                    $content = "La tua password è stata impostata con successo! <br>
                    <a href='$PATH->site/backend/'>Accedi</a><br>
                    <br>
                    Se non sei stato tu a richiederlo contattaci: info@wonderimage.it";

                    if(sendMail('noreply@wonderimage.it', $USER->email, "Password impostata", $content)) {
                        header("Location: ../login/?alert=611");
                    };

                } else {

                    $ALERT = 916;

                }

            }

        }
        
    }
    
?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambio password</title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>
    
</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>

    <div class="position-absolute w-75 top-50 start-50 translate-middle" style="max-width: 400px">
        <form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
            <wi-card>
                
                <div class="d-grid col-12 mx-auto">
                    <img class="position-relative w-75 start-50 translate-middle-x" src="<?=$PATH->app?>/assets/logos/Wonder-Image-White.png" alt="Wonder Image">
                </div>

                <div class="col-12">
                    <?=password('Password', 'password', 'required'); ?>
                </div>

                <div class="d-grid col-8 mx-auto">
                    <?=submit('Imposta la password', 'set-password'); ?>
                </div>

            </wi-card>
        </form>

        <div class="row mt-3">
            <div class="col-12 text-center">
                <a class="text-dark" href="../login/">Accedi</a>
            </div>
        </div>

    </div>

    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>