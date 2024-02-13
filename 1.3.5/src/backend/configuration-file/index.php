<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $HTACCESS_PATH = $ROOT."/.htaccess";
    $ROBOTS_PATH = $ROOT."/robots.txt";

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Modifica i file di configurazione";

    if (isset($_POST['modify'])) {
        
        $htaccess = $_POST['htaccess'];
        $robots = $_POST['robots'];

        $FILE = fopen($HTACCESS_PATH, "w");
        fwrite($FILE, $htaccess);
        fclose($FILE);

        $FILE = fopen($ROBOTS_PATH, "w");
        fwrite($FILE, $robots);
        fclose($FILE);

        header("Location: ?alert=654");

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
                            <h6>File .htaccess</h6>
                        </div>
                        <div class="col-12">
                            <?=textarea('Editor', 'htaccess', null, null, file_get_contents($HTACCESS_PATH)) ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>File robots.txt</h6>
                        </div>
                        <div class="col-12">
                            <?=textarea('Editor', 'robots', null, null, file_get_contents($ROBOTS_PATH)) ?>
                        </div>
                    </wi-card>

                </div>
            </div>

            <div class="col-3">
                <div class="row g-3">

                    <wi-card class="col-12">
                        <div class="col-12">
                            <?=submit('Modifica', 'modify'); ?>
                        </div>
                    </wi-card>

                </div>
            </div>

        </div>
    </form>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

    <script>
        $(document).ready(function () {

            document.querySelector("textarea[name='htaccess']").style.height = '500px';
            document.querySelector("textarea[name='robots']").style.height = '500px';

        });
    </script>

</body>
</html>