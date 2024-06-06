<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if (array_key_exists('stats', $DB->database)) {

        $mysqli = $MYSQLI_CONNECTION['stats'];

        $FROM = date('Y-m-d H', strtotime('-24 hours')).':00:00';
        $TO = date('Y-m-d H').':00:00';

        $N_VIEW = number_format(sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'")->Nrow, 0, '.');
        $N_VISITOR = number_format(sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT visitor_id")->Nrow, 0, '.');
        $N_SESSION = number_format(sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO'", null, null, null, "DISTINCT session_id")->Nrow, 0, '.');
        $N_HTTPS = number_format(sqlSelect('visitors_log', "`creation` BETWEEN '$FROM' AND '$TO' AND `https` = 'on'")->Nrow, 0, '.');
        $N_HTTP = number_format($N_VIEW - $N_HTTPS, 0, '.');

        $FROM = date('Y-m-d ').' 00:00:00';
        $TO = date('Y-m-d ').' 23:59:59';

        $MOST_PAGE_VIEW = sqlSelect('url_vr_dbd', "`creation` BETWEEN '$FROM' AND '$TO'", 20, 'visitors', 'DESC');

        $mysqli = $MYSQLI_CONNECTION['main'];

    }
    
?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>
    
    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <div class="row g-3">

        <wi-card class="col-3">

            <?php

                foreach ($NAV_BACKEND as $navs) {

                    $titleNav = isset($navs['title']) ? $navs['title'] : 'ND';
                    $folderNav = isset($navs['folder']) ? $navs['folder'] : 'home';
                    $iconNav = isset($navs['icon']) ? $navs['icon'] : 'bi-bug';
                    $fileNav = isset($navs['file']) ? $navs['file'] : '';
                    $authNav = isset($navs['authority']) ? $navs['authority'] : [];
                    $subNav = isset($navs['subnavs']) ? $navs['subnavs'] : [];

                    if ($titleNav != 'Home') {
                        if (!$authNav || count(array_intersect($authNav, $USER->authority)) >= 1) {

                            if (empty($subNav)) {

                                $subnavsList = '';

                            }else{
                                
                                $subNavs = "";

                                foreach ($subNav as $sub) {
                                    
                                    $titleSub = isset($sub['title']) ? $sub['title'] : 'ND';
                                    $folderSub = isset($sub['folder']) ? $sub['folder'] : 'home';
                                    $authSub = isset($sub['authority']) ? $sub['authority'] : [];
                                    $fileSub = isset($sub['file']) ? $sub['file'] : '';

                                    if (!$authSub || count(array_intersect($authSub, $USER->authority)) >= 1) {
                                        $subNavs .= "<a class='list-group-item list-group-item-action' href='$PATH->site/backend/$folderSub/$fileSub'>$titleSub <i class='bi bi-chevron-right float-end'></i></a>";
                                    }
                                    
                                }

                                $subnavsList = "$subNavs";

                            }

                            if (!empty($subnavsList)) {

                                echo "
                                <div class='list-group ps-2'>
                                    <li class='list-group-item list-group-item-dark'><i class='bi $iconNav'></i> $titleNav</li>
                                    $subnavsList
                                </div>";

                            }else{

                                echo "
                                <div class='list-group ps-2'>
                                    <a href='$PATH->site/backend/$folderNav/$fileNav' type='button' class='list-group-item list-group-item-dark list-group-item-action'><i class='bi $iconNav mr-2'></i> $titleNav <i class='bi bi-chevron-right float-end'></i></a>
                                </div>";

                            }

                        }
                    }

                }

            ?>
        </wi-card>

        <div class="col-9">
            <div class="row g-3">

                <?php if (array_key_exists('stats', $DB->database)) { ?>
                <wi-card class="col-12">
                    <div class="col-12">
                        <h6>Ultime 24 ore</h6>
                    </div>
                    <div class="col-4">
                        <h1><?=$N_VIEW?></h1>
                        <h6 class="fw-light">Visualizzazioni di pagina</h6>
                    </div>
                    <div class="col-4">
                        <h1><?=$N_VISITOR?></h1>
                        <h6 class="fw-light">Utenti</h6>
                    </div>
                    <div class="col-4">
                        <h1><?=$N_SESSION?></h1>
                        <h6 class="fw-light">Sessioni</h6>
                    </div>
                </wi-card>

                <wi-card class="col-8">
                    <div class="col-12">
                        <h6>Pagine più visitate di ieri</h6>
                    </div>
                    <div class="col-12 overflow-scroll">
                        <table id="page-view-table" class="table table-hover" style="max-width:100%">
                            <thead>
                                <tr>
                                    <th scope="col">Url</th>
                                    <th scope="col">Visualizzazioni</th>
                                    <th scope="col">Utenti</th>
                                    <th scope="col">Sessioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php

                                    foreach ($MOST_PAGE_VIEW->row as $key => $row) {

                                        $url = $row['url'];
                                        $view = $row['visitors'];
                                        $visitor = $row['visitors_unique'];
                                        $session = $row['sessions'];

                                        echo "
                                        <tr>
                                            <td><a href='$PATH->site$url' target='_blank' rel='noopener noreferrer' class='text-dark'>$url</a></td>
                                            <td>$view</td>
                                            <td>$visitor</td>
                                            <td>$session</td>
                                        </tr>";

                                    }

                                ?>
                            </tbody>
                        </table>

                        <script>

                            const tableCondition = {
                                rowReorder: true,
                                searching: false,
                                lengthChange: false,
                                pageLength: 5,
                                pagingType: 'simple_numbers',
                                language: {
                                    url: 'https://cdn.datatables.net/plug-ins/1.12.1/i18n/it-IT.json'
                                }
                            };
                            
                        </script>

                    </div>
                </wi-card>

                <wi-card class="col-4">
                    <div class="col-12">
                        <h6>Sicurezza</h6>
                    </div>
                    <div class="col-12">

                        <canvas id="https" class="w-100"></canvas>

                        <script>

                            const data = {
                                labels: [
                                    'HTTPS',
                                    'HTTP'
                                ],
                                datasets: [
                                    {
                                        label: 'Visualizzazioni',
                                        data: [<?=$N_HTTPS?>, <?=$N_HTTP?>],
                                        backgroundColor: [
                                            '<?=bootstrapColor('primary')?>',
                                            '<?=bootstrapColor('warning')?>'
                                        ],
                                    }
                                ]
                            };

                            const config = {
                                type: 'doughnut',
                                data: data,
                                options: {
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                        },
                                    }
                                }
                            };

                        </script>

                    </div>
                </wi-card>
                <?php } ?>

                <wi-card class="col-12">
                    <div class="col-12">
                        <h6>Contatti</h6>
                    </div>
                    <div class="col-12">
                        Cellulare: <a href="tel:393911220336" target="_blank" rel="noopener noreferrer">391 1220336</a> <br>
                        Whatsapp:  <a href="https://wa.me/3911220336?text=Ciao%20Andrea%20ho%20bisogno%20di%20un%20aiuto" target="_blank" rel="noopener noreferrer">391 1220336</a> <br>
                        Email:     <a href="mailto:marinoni@wonderimage.it" target="_blank" rel="noopener noreferrer">marinoni@wonderimage.it</a>
                    </div>
                </wi-card>

            </div>
        </div>

    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

    <?php if (is_array($DB->database) && array_key_exists('stats', $DB->database)) { ?>
    <script>

        $(document).ready(function () {

            new DataTable('#page-view-table', tableCondition);

            const myChart = new Chart(
                document.getElementById('https'),
                config
            );
            
        });

    </script>
    <?php } ?>

</body>
</html>