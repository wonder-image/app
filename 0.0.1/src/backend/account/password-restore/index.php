<?php

    $BACKEND = true;
    $PRIVATE = false;

    $ROOT = $_SERVER['DOCUMENT_ROOT'];

    include $ROOT.'/app/wonder-image.php';
    
    // Control link validity
        $time = strtotime("now");
        $restriction = $_GET['r'];

        if (empty($restriction)) {
            
            header("Location: ../login/?alert=913");

        } else {

            $restriction = json_decode(base64_decode($restriction));

            $USER = infoUser($restriction->user_id);
            $validity = $restriction->validity;

            if ($validity <= $time) { $ALERT = 914; }

        }
    // 

    if (isset($_POST['restore'])) {

        $VALUES = $_POST;

        $password = hashPassword($_POST['password']);

        if ($validity <= $time) { $ALERT = 914; }

        if (empty($ALERT)) {

            $VERIFY = verifyUser('id', $USER->id, 'backend');

            if ($VERIFY->response) {
                
                $USER = $VERIFY->user;

                sqlModify('user', ['password' => $password], 'id', $USER->id);

                $content = "La tua password è stata modificata con successo! <br>
                <br>
                È possibile cambiare la tua password in qualsiasi momento in Login -> Account -> Modifica password oppure premi <br><a href='$PATH->backend/account/'>qui</a><br>
                <br>
                Se non sei stato tu a richiederlo contattaci: marinoni@wonderimage.it";

                if(sendMail('noreply@wonderimage.it', $USER->email, "Password modificata", $content)) {
                    header("Location: ../login/?alert=602");
                };

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

    <!-- Files to include -->
    <?php include $ROOT_APP.'/utility/backend/include-top.php'; ?>
    
</head>
<body>

    <div class="position-absolute w-75 top-50 start-50 translate-middle" style="max-width: 400px">
        <form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
            <wi-card>
                
                <div class="d-grid col-12 mx-auto">
                    <img class="position-relative w-75 start-50 translate-middle-x" src="<?=$PATH->app?>/assets/logos/Wonder-Image.png" alt="Wonder Image">
                </div>

                <div class="col-12">
                    <?=text('Username', 'username', 'disabled', $USER->username); ?>
                </div>

                <div class="col-12">
                    <?=password('Nuova password', 'password', 'required'); ?>
                </div>

                <div class="d-grid col-8 mx-auto">
                    <?=submit('Cambia password', 'restore'); ?>
                </div>

            </wi-card>
        </form>

        <div class="row mt-3">
            <div class="col-12 text-center">
                <a class="text-dark" href="../login/">Accedi</a>
            </div>
        </div>

    </div>

    <!-- Files to include -->
    <?php include $ROOT_APP.'/utility/backend/include-bottom.php'; ?>

</body>
</html>