<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $INFO_PAGE = (object) [];
    $INFO_PAGE->title = "Credenziali";
    $INFO_PAGE->table = $TABLE->SECURITY;
    $INFO_PAGE->tableName = "security";

    $SQL = sqlSelect($INFO_PAGE->tableName, ['id' => 1], 1);
    $VALUES = $SQL->row;

    if (isset($_POST['modify'])) {
        
        $VALUES = formToArray($INFO_PAGE->tableName, $_POST, $INFO_PAGE->table);
        
        if (empty($ALERT)) { sqlModify($INFO_PAGE->tableName, $VALUES, 'id', 1); }
        if (empty($ALERT)) { header("Location: ?alert=653"); }

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
                <h3>
                    <?=$INFO_PAGE->title?>
                    <?=submit('Modifica', 'modify', 'float-end'); ?>
                </h3>
            </wi-card>

            <div class="col-9">
                <div class="row g-3">

                    <wi-card class="col-12">
                        <h6 class="col-12"> Wonder Image </h6>
                        <div class="col-12">
                            <?=text('Api Key', 'api_key', 'disabled'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <h6 class="col-12"> Google Cloud Platform </h6>
                        <div class="col-12">
                            Segui la documentazione <a href="https://wonder-image.gitbook.io/app/altro/servizi/google-cloud-platform" target="_blank" rel="noopener noreferrer">clicca qui</a>.
                        </div>
                        <div class="col-2">
                            <?=text('ID Progetto', 'gcp_project_id'); ?>
                        </div>
                        <div class="col-5">
                            <?=text('Chiave Privata', 'gcp_api_key'); ?>
                        </div>
                        <div class="col-5">
                            <?=text('Chiave Pubblica', 'gcp_client_api_key'); ?>
                        </div>

                        <div class="col-6">
                            <div class="row g-3">
                                <h6 class="col-12">Google reCAPTCHA*</h6>
                                <div class="col-12">
                                    <?=text('Chiave Sito', 'g_recaptcha_site_key'); ?>
                                </div> 
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="row g-3">
                                <h6 class="col-12">Google Place*</h6>
                                <div class="col-12">
                                    <?=text('Place ID', 'g_maps_place_id'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            *Per utilizzare questa funzione è necessario compilare i campi di <b>Google Cloud Platform</b>
                        </div>

                    </wi-card>

                    <wi-card class="col-6">
                        <h6 class="col-12"> Server mail </h6>
                        <div class="col-8">
                            <?=text('Host', 'mail_host', 'required'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('Porta', 'mail_port', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Username', 'mail_username', 'required'); ?>
                        </div>
                        <div class="col-12">
                            <?=password('Password', 'mail_password', 'required'); ?>
                        </div>
                    </wi-card>

                </div>
            </div>

            <div class="col-3">
                <div class="row g-3">

                    <wi-card>
                        <h6 class="col-12"> Stripe </h6>

                        <div class="col-12">
                            <?=select('Ambiente', 'stripe_test', [ 'false' => 'Produzione', 'true' => 'Test' ], null, 'required'); ?>
                        </div>

                        <h6>Produzione</h6>
                        <?php if (empty($VALUES['stripe_account_id'])): ?>
                        <div class="col-12">
                            <a href="<?=$PATH->appApi?>/service/stripe/onboarding/?account=production" class="w-100 btn btn-primary">
                                Effettua connessione
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="col-12">
                            <?=text('Account ID', 'stripe_account_id', 'readonly'); ?>
                        </div>
                        <div class="col-12">
                            <a href="<?=$PATH->appApi?>/service/stripe/onboarding/?account=production" class="w-100 btn btn-primary">
                                Modifica i dati
                            </a>
                        </div>
                        <!-- <div class="col-6">
                            <a href="<?=$PATH->appApi?>/service/stripe/onboarding/?ca=true" class="w-100 btn btn-warning">
                                Cambia account
                            </a>
                        </div> -->
                        <?php endif; ?>

                        <h6>Test</h6>
                        <?php if (empty($VALUES['stripe_test_account_id'])): ?>
                        <div class="col-12">
                            <a href="<?=$PATH->appApi?>/service/stripe/onboarding/?account=test" class="w-100 btn btn-primary">
                                Effettua connessione
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="col-12">
                            <?=text('Account ID', 'stripe_test_account_id', 'readonly'); ?>
                        </div>
                        <div class="col-12">
                            <a href="<?=$PATH->appApi?>/service/stripe/onboarding/?account=test" class="w-100 btn btn-primary">
                                Modifica i dati
                            </a>
                        </div>
                        <!-- <div class="col-6">
                            <a href="<?=$PATH->appApi?>/service/stripe/onboarding/?ca=true" class="w-100 btn btn-warning">
                                Cambia account
                            </a>
                        </div> -->
                        <?php endif; ?>

                    </wi-card>

                    <wi-card>
                        <h6 class="col-12"> Fatture in Cloud </h6>
                        <div class="col-12">
                            <?=text('Codice cliente', 'fatture_in_cloud_company_id'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Token', 'fatture_in_cloud_token'); ?>
                        </div>
                        <div class="col-12">
                            Segui la documentazione <a href="https://wonder-image.gitbook.io/app/altro/servizi/fatture-in-cloud" target="_blank" rel="noopener noreferrer">clicca qui</a>.
                        </div>
                        <!-- <div class="col-12">
                            <a href="<?=$PATH->appApi?>/service/fatture-in-cloud/onboarding/" class="w-100 btn btn-primary">
                                <?php empty($VALUES['fatture_in_cloud_token']) ? "Effettua connessione" : "Cambia account" ; ?>
                            </a>
                        </div> -->
                    </wi-card>

                </div>
            </div>

        </div>
    </form>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>