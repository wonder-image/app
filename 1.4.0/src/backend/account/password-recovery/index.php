<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if (isset($_POST['recovery'])) {

        $VALUES = $_POST;
        $VERIFY = verifyUser('username', $_POST['username'], 'backend');

        if ($VERIFY->response) {
            
            $USER = $VERIFY->user;

            $restriction = [
                "user_id" => $USER->id,
                "validity" => strtotime("+30 minutes")
            ];

            $restriction = base64_encode(json_encode($restriction));

            $content = "Ecco il link per modificare la tua password.<br>
            <a href='$PATH->backend/account/password-restore/?r=$restriction'>Clicca qui</a><br>
            <br>
            Se non sei stato tu a richiederlo contattaci: marinoni@wonderimage.it";

            if(sendMail('noreply@wonderimage.it', $USER->email, "Recupero password", $content)) {
                header("Location: ../login/?alert=601");
            };
                    
        }
        
    }
    
?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recupero password</title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>
    
</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>

    <div class="position-absolute w-75 top-50 start-50 translate-middle" style="max-width: 400px">

        <form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
            <wi-card>
                
                <div class="d-grid col-12 mx-auto">
                    <img id="be-logo-black" src="<?=$DEFAULT->BeLogoBlack?>" class="position-relative w-75 start-50 translate-middle-x d-none" ><img id="be-logo-white" src="<?=$DEFAULT->BeLogoWhite?>" class="position-relative w-75 start-50 translate-middle-x d-none">
                </div>

                <div class="col-12">
                    <?=text('Username', 'username', 'required'); ?>
                </div>

                <div class="d-grid col-8 mx-auto">
                    <?=submit('Recupera password', 'recovery'); ?>
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
    
    <script> $(document).ready(function () { bootstrapTheme(localStorage.theme); }); </script>

</body>
</html>