<?php

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Scarica tabelle";

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$INFO_PAGE->title?></title>

    <!-- Files to include -->
    <?php include $ROOT_APP.'/utility/backend/include-top.php'; ?>

</head>
<body>
    
    <?php include $ROOT_APP.'/utility/backend/header.php' ?>

    <div class="row g-3">

        <wi-card class="col-12">
            <h3>Scarica tabelle</h3>
        </wi-card>

        <wi-card class="col-12">
            <div class="col-4">
                <?php

                    $checkbox = [];
                    foreach ($TABLE as $key => $value) {
                        $k = strtolower($key);
                        $checkbox[$k] = $key;
                    }

                    echo check('Tabella', 'table', $checkbox, 'required', 'radio', true);

                ?>
            </div>
            <div class="col-4">
                <?php

                    $checkbox = [
                        "csv" => "Csv",
                        "xls" => "Excel"
                    ];

                    echo select('Formato', 'format', $checkbox, 'old', 'required');

                ?>
            </div>
            <div class="col-12">
                <!-- Crea bottone onclick go api download -->
                <button class="btn btn-dark" onclick="download()">
                    Scarica
                </button>
            </div>
        </wi-card>

    </div>

    <script>

        function download() {
            
            var table = '';
            var format = '';

            document.querySelectorAll("input[name=table]").forEach(element => {
                if (element.checked) {
                    table = element.value;
                }
            });

            document.querySelectorAll("select[name=format]").forEach(element => {
                if (element.value != '') {
                    format = element.value;
                }
            });

            var link = pathApp+'/api/export.php?table='+table+'&format='+format;
            window.open(link, '_blank')

        }

    </script>


    <?php include $ROOT_APP.'/utility/backend/footer.php' ?>
    <?php include $ROOT_APP.'/utility/backend/include-bottom.php' ?>


</body>
</html>