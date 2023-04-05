<?php

    $BACKEND = true;
    $PERMIT = ['admin'];
    $PRIVATE = true;

    $ROOT = $_SERVER['DOCUMENT_ROOT'];

    include $ROOT.'/app/wonder-image.php';
    
    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Dati aziendali";
    $INFO_PAGE->table = ["society", "society_address", "society_legal_address", "society_social"];

    $VALUES = [];

    foreach ($INFO_PAGE->table  as $key => $table) {
        
        $SQL = sqlSelect($table, ['id' => 1], 1);
        $VALUES = array_merge($VALUES, $SQL->row);

    }

    if (isset($_POST['modify'])) {

        $VALUES = [];

        foreach ($INFO_PAGE->table  as $key => $table) {
            
            $t = strtoupper($table);
            $VALUE = formToArray($table, $_POST, $TABLE->$t);

            $VALUES = array_merge($VALUES, $VALUE);
     
            if (empty($ALERT)) {
                sqlModify($table, $VALUE, 'id', 1);
            }
    
        }

    }

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
    
    <form class="col-12" action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
        <div class="row g-3">

            <wi-card class="col-12">
                <h3>Impostazioni dati aziendali</h3>
            </wi-card>

            <div class="col-9">
                <div class="row g-3">

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Dati</h6>
                        </div>
                        <div class="col-8">
                            <?=text('Nome', 'name'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Email', 'email'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Telefono', 'tel'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('Cellulare', 'cel'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Dati legali</h6>
                        </div>
                        <div class="col-8">
                            <?=text('Nome legale', 'legal_name'); ?>
                        </div>
                        <div class="col-4">
                            <?=price('C.Sociale', 'share_capital'); ?>
                        </div>
                        <div class="col-8">
                            <?=text('Pec', 'pec'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('R.E.A.', 'rea'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('P.Iva', 'pi'); ?>
                        </div>
                        <div class="col-6">
                            <?=text('C.Fiscale', 'cf'); ?>
                        </div>
                    </wi-card>
                    

                    <wi-card class="col-6">
                        <div class="col-12">
                            <h6>Indirizzo legale</h6>
                        </div>
                        <div class="col-6">
                            <?php

                                $checkbox = geoCountry('EU');
                                echo check('Paese', 'legal_country', ['IT' => "Italia"], "readonly", 'radio', true, 'IT');

                            ?>
                        </div>
                        <div class="col-6">
                            <?php

                                $checkbox = geoProvince('IT');
                                echo check('Provincia', 'legal_province', $checkbox, "", 'radio', true);

                            ?>
                        </div>
                        <div class="col-8">
                            <?=text('Città', 'legal_city'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('Cap', 'legal_cap'); ?>
                        </div>
                        <div class="col-10">
                            <?=text('Via', 'legal_street'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Civico', 'legal_number'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Altro', 'legal_more'); ?>
                        </div>
                        <div class="col-12">
                            <?=url('Link gmaps', 'legal_gmaps'); ?>
                        </div>
                    </wi-card>

                    <wi-card class="col-6">
                        <div class="col-12">
                            <h6>Indirizzo aziendale</h6>
                        </div>
                        <div class="col-6">
                            <?php

                                $checkbox = geoCountry('EU');
                                echo check('Paese', 'country', ['IT' => "Italia"], "", 'radio', true, 'IT');

                            ?>
                        </div>
                        <div class="col-6">
                            <?php

                                $checkbox = geoProvince('IT');
                                echo check('Provincia', 'province', $checkbox, "", 'radio', true);

                            ?>
                        </div>
                        <div class="col-8">
                            <?=text('Città', 'city'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('Cap', 'cap'); ?>
                        </div>
                        <div class="col-10">
                            <?=text('Via', 'street'); ?>
                        </div>
                        <div class="col-2">
                            <?=text('Civico', 'number'); ?>
                        </div>
                        <div class="col-12">
                            <?=text('Altro', 'more'); ?>
                        </div>
                        <div class="col-12">
                            <?=url('Link gmaps', 'gmaps'); ?>
                        </div>
                    </wi-card>

                </div>
            </div>

            <wi-card class="col-3">
                <div class="col-12">
                    <h6>Link</h6>
                </div>
                <?php

                    foreach ($TABLE->SOCIETY_SOCIAL as $key => $value) {
                        
                        $label = $value['input']['label'];

                        $input = url($label, $key);

                        echo "<div class='col-12'>$input</div>";

                    }

                ?>
                <div class="col-12">
                    <?=submit('Modifica dati', 'modify'); ?>
                </div>
            </wi-card>

        </div>
    </form>


    <?php include $ROOT_APP.'/utility/backend/footer.php' ?>
    <?php include $ROOT_APP.'/utility/backend/include-bottom.php' ?>


</body>
</html>