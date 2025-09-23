<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Dati aziendali";
    $INFO_PAGE->table = ["society", "society_address", "society_legal_address", "society_social"];

    $VALUES = [];

    foreach ($INFO_PAGE->table  as $key => $table) {
        
        $SQL = sqlSelect($table, ['id' => 1], 1);
        $VALUES = array_merge($VALUES, $SQL->row);

    }

    $TIMETABLES = [];

    $TIME = empty($VALUES['timetable']) ? [] : json_decode($VALUES['timetable'], true);

    $i = 1;

    foreach ($TIME as $day => $value) {
        foreach ($value as $k => $v) {

            array_push($TIMETABLES, [
                'position' => $i,
                'time-day' => $day,
                'time-from' => $v['from'],
                'time-to' => $v['to'],
            ]);

            $i++;

        }
    }

    if (isset($_POST['modify'])) {

        $VALUES = [];

        $TIMETABLES = [];
        $JSON_TIMETABLES = [];

        foreach ($_POST['time-day'] as $key => $day) {
            if (!empty($_POST['time-from'][$key]) && !empty($_POST['time-to'][$key])) {

                $position = $_POST['position'][$key];
                $from = $_POST['time-from'][$key];
                $to = $_POST['time-to'][$key];

                $TIMETABLES[$position] = [
                    'position' => $position,
                    'time-day' => $day,
                    'time-from' => $from,
                    'time-to' => $to,
                ];
        
            }
        }

        # Ordino per posizione
            ksort($TIMETABLES);

        # Creo array per SQL
            foreach ($TIMETABLES as $key => $value) {
                
                $day = $value['time-day'];
                $from = $value['time-from'];
                $to = $value['time-to'];

                $TIME = [
                    'from' => $from,
                    'to' => $to
                ];

                if (!array_key_exists($day, $JSON_TIMETABLES)) { $JSON_TIMETABLES[$day] = []; }

                array_push($JSON_TIMETABLES[$day], $TIME);

            }

            $_POST['timetable'] = json_encode($JSON_TIMETABLES, true);

        unset($_POST['id']);
        unset($_POST['position']);
        unset($_POST['time-day']);
        unset($_POST['time-from']);
        unset($_POST['time-to']);

        foreach ($INFO_PAGE->table  as $key => $table) {
            
            $t = strtoupper($table);
            $VALUE = formToArray($table, $_POST, $TABLE->$t);

            $VALUES = array_merge($VALUES, $VALUE);
     
            if (empty($ALERT)) { sqlModify($table, $VALUE, 'id', 1); }
            if (empty($ALERT)) { header("Location: ?alert=651"); }
            
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
                        <div class="col-3">
                            <?=text('Telefono', 'tel'); ?>
                        </div>
                        <div class="col-3">
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
                            <?=text('SDI', 'sdi'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('R.E.A.', 'rea'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('P.Iva', 'pi'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('C.Fiscale', 'cf'); ?>
                        </div>
                    </wi-card>
                    
                    <wi-card class="col-6">
                        <div class="col-12">
                            <h6>Indirizzo legale</h6>
                        </div>
                        <div class="col-6">
                            <?=inputCountry('Paese', 'legal_country', $VALUES['legal_country'] ?? 'IT', 'legal_province')?>
                        </div>
                        <div class="col-6">
                            <?=inputStates('Provincia', 'legal_province', $VALUES['legal_country'] ?? 'IT');?>
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
                            <?=inputCountry('Paese', 'country', $VALUES['country'] ?? 'IT', 'province')?>
                        </div>
                        <div class="col-6">
                            <?=inputStates('Provincia', 'province', $VALUES['country'] ?? 'IT');?>
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

                    <wi-card class="col-12">
                        <?php

                            $DAY = [ "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun" ];
                            $DAY_OPTION = [];

                            foreach ($DAY as $day) { $DAY_OPTION[$day] = translateDate($day, 'day'); }

                            $INPUT = [
                                'time-day' => [
                                    'label' => 'Giorno',
                                    'type' => 'select',
                                    'option' => $DAY_OPTION,
                                    'attribute' => '',
                                    'col' => 3
                                ],
                                'time-from' => [
                                    'label' => 'Da (08:00)',
                                    'type' => 'text',
                                    'attribute' => '',
                                    'col' => 3
                                ],
                                'time-to' => [
                                    'label' => 'A (17:30)',
                                    'type' => 'text',
                                    'attribute' => '',
                                    'col' => 3
                                ],
                            ];

                            echo sortableInput('Orari', 'list-copy', $INPUT, isset($TIMETABLES) ? $TIMETABLES : null);
                            
                        ?>
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

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>