<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";
    
    $redirect = !empty($_GET['redirect']) ? $PAGE->redirect : $PATH->backend."/home/";

    if (isset($_POST['login'])) {

        if (authenticateUser("username", $_POST['username'], $_POST['password'], 'backend')) {
            header("Location: $redirect");
        } else {
            $username = $_POST['username'];
        }
        
    }
    
?>
<!DOCTYPE html>
<html lang="it">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>
    
</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>

    <div class="position-absolute w-75 top-50 start-50 translate-middle" style="max-width: 400px">
        
        <form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
            <wi-card>
                
                <div class="d-grid col-12 mx-auto">
                    <img class="position-relative w-75 start-50 translate-middle-x" src="<?=$PATH->app?>/assets/logos/Wonder-Image.png" alt="Wonder Image">
                </div>
                
                <div class="col-12">
                    <?=text('Username', 'username', 'required', isset($username) ? $username : ''); ?>
                </div>

                <div class="col-12">
                    <?=password('Password', 'password', 'required', isset($password) ? $password : ''); ?>
                </div>

                <div class="d-grid col-8 mx-auto">
                    <?=submit('Accedi', 'login'); ?>
                </div>

            </wi-card>
        </form>

        <div class="row mt-3">
            <div class="col-12 text-center">
                <a class="text-dark" href="../password-recovery/">Recupera password</a>
            </div>
        </div>

    </div>

    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>