<!DOCTYPE html>
<html lang="it">
    <head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aggiornamento wonder-image/app</title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>

    <div class="position-absolute w-75 start-50 translate-middle-x mt-4" style="max-width: 450px">

        <wi-card>
        <?php


            echo "<h4 class='col-12 mb-2'>wonder-image/app <b>v$APP_VERSION</b></h6>";

            # Aggiorno le tabelle

                foreach ($TABLE as $table => $value) {
                    
                    $table_name = strtolower($table);
                    $table_column = $value;

                    sqlTable($table_name, $table_column);
                    
                }

                echo '<h5 class="col-12"><i class="bi bi-check2 text-success me-1"></i> Tabelle</h5>';

            # Aggiorno le righe

                $files = scanParentDir("$ROOT_APP/build/row/");
                foreach ($files as $file) { include "$ROOT_APP/build/row/$file"; }

                $files = scanParentDir("$ROOT/custom/build/row/");
                foreach ($files as $file) { include "$ROOT/custom/build/row/$file"; }

                echo '<h5 class="col-12"><i class="bi bi-check2 text-success me-1"></i> Righe tabelle</h5>';

            # Aggiungo pagine

                $files = scanParentDir("$ROOT_APP/build/page/");
                foreach ($files as $file) { include "$ROOT_APP/build/page/$file"; }

                $files = scanParentDir("$ROOT/custom/build/page/");
                foreach ($files as $file) { include "$ROOT/custom/build/page/$file"; }

                echo '<h5 class="col-12"><i class="bi bi-check2 text-success me-1"></i> Pagine</h5>';

            # Verifico validità api

                $API_STATUS = json_decode(wiApi('/auth/status/'), true);

                if ($API_STATUS['success']) {
                    
                    if ($API_STATUS['response']['active'] == true) {

                        wiApi("/auth/update/", [
                            "site_name" => $PAGE->domain,
                            "app_version" => $APP_VERSION
                        ]);

                        # Se le credenziali sono attive aggiorno la versione
                        echo '<h5 class="col-12"><i class="bi bi-check2 text-success me-1"></i> '.$API_STATUS['response']['description'].'</h5>';

                    } else {

                        # Se le credenziali non sono attive mando la richiesta di attivazione
                        $API_REQUEST = json_decode(wiApi("/auth/request/", [
                            "site_name" => $PAGE->domain,
                            "app_version" => $APP_VERSION
                        ]), true);

                        if ($API_REQUEST['success']) {
                            echo '<h5 class="col-12"><i class="bi bi-x-lg text-danger me-1"></i> '.$API_REQUEST['response'].'</h5>';
                        } else {
                            echo '<h5 class="col-12"><i class="bi bi-x-lg text-danger me-1"></i> '.$API_REQUEST['response'].'</h5>';
                        }

                    }
                    
                } else {

                    echo '<h5 class="col-12"><i class="bi bi-x-lg text-danger me-1"></i> '.$API_STATUS['response'].'</h5>';

                }

        ?>
        </wi-card>

    </div>

    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>