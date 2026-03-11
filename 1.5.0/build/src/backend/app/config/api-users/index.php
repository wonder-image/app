<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    use Wonder\Plugin\Custom\Input\SortableInput;

    require_once "set-up.php";
    
    if (!empty($_GET['modify'])) {

        $TITLE = "Modifica $TEXT->titleS";

        $SQL = sqlSelect($NAME->table, ['id' => $_GET['modify']], 1);
        $API_SQL = sqlSelect('api_users', ['user_id' => $_GET['modify']], 1);
        $VALUES = array_merge($SQL->row, $API_SQL->row);

    } else {

        $TITLE = "Aggiungi $TEXT->titleS";

    }

    $REDIRECT = (!empty($PAGE->redirect)) ? $PAGE->redirect : "$PATH->backend/$NAME->folder/list.php";

    if (isset($_POST['upload']) || isset($_POST['upload-add'])) {

        if (isset($_GET['modify'])) {
            $MODIFY_ID = $_GET['modify'];
        } else if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
            $MODIFY_ID = $_POST['user_id'];
        } else {
            $MODIFY_ID = null;
        }

        $POST = array_merge($_POST, $_FILES);
        $UPLOAD = user($POST, $MODIFY_ID); 

        if (empty($ALERT)) {
            if (isset($_POST['upload-add'])) {
                header("Location: $PATH->backend/$NAME->folder/index.php?redirect=$PAGE->redirectBase64");
            } else {
                header("Location: $REDIRECT");
            }
        }

    }

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$TITLE?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>
    
    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <form action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">

        <input type="hidden" name="area" value="api">
        <input type="hidden" name="password" value="<?=code(2, 'letters').'-'.code(4, 'numbers');?>">

        <div class="row g-3">

            <wi-card class="col-12">
                <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$TITLE?></h3>
            </wi-card>

            <div class="col-9">
                <div class="row g-3">

                    <?php if (empty($_GET['modify']) && (in_array('admin', $USER->authority))) { ?>
                    <wi-card class="col-12">
                        <div class="col-12">
                            <h6>Crea utente da email già esistente</h6>
                        </div>
                        <div class="col-6">
                            <?php

                                $OPTION = [
                                    '' => 'No'
                                ];

                                foreach (sqlSelect('user', "`area` NOT LIKE '%api%' and `deleted` = 'false'")->row as $key => $value) {

                                    $id = $value['id'];
                                    $username = $value['username'];

                                    $OPTION[$id] = $username;

                                }

                                echo check('Username', 'user_id', $OPTION, "onclick=\"if (this.value != '') { disableInput('user'); } else { enabledInput('user'); }\"", 'radio', true); 
                                
                            ?>
                        </div>
                    </wi-card>
                    <?php } ?>
                    
                    <wi-card class="col-12">

                        <div class="col-3">
                            <div class="row g-3">

                                <div class="col-12">
                                    <?=inputFileDragDrop('', 'profile_picture', 'profile', 'image')?>
                                </div>

                            </div>
                        </div>
                        <div class="col-9">
                            <div class="row g-3">
                                <div class="col-4">
                                    <?=text('Nome', 'name', 'required'); ?>
                                </div>
                                <div class="col-4">
                                    <?=text('Cognome', 'surname', 'required'); ?>
                                </div>
                                <div class="col-4">
                                    <?php

                                        $option = [];

                                        foreach ($DEFAULT->colorUser as $key => $color) {
                                            if ($color['active']) {
                                                $option[$key] = $color['name'];
                                            }
                                        }

                                        echo select('Colore', 'color', $option);

                                    ?>
                                </div>
                                <div class="col-6">
                                    <?=text('Username', 'username', 'required'); ?>
                                </div>
                                <div class="col-6">
                                    <?=phone('Cellulare', 'phone'); ?>
                                </div>
                                <div class="col-12">
                                    <?=email('Email', 'email', 'required'); ?>
                                </div>
                            </div>
                        </div>
                    </wi-card>

                </div>
            </div>

            <div class="col-3">
                <div class="row g-3">

                    <wi-card>
                        <?php

                            $IP = new SortableInput('allowed_domains', isset($VALUES['allowed_domains']) ? array_map(fn($domain) => ['allowed_domains' => $domain], json_decode($VALUES['allowed_domains'])) : null);

                            $IP->Title("Domini");
                            $IP->Position(false);
                            
                            $IP->Column("allowed_domains", "Dominio", "text", null, 11);

                            echo $IP->Generate();

                        ?>
                    </wi-card>

                    <wi-card>
                        <?php

                            $IP = new SortableInput('allowed_ips', isset($VALUES['allowed_ips']) ? array_map(fn($domain) => ['allowed_ips' => $domain], json_decode($VALUES['allowed_ips'])) : null);

                            $IP->Title("Indirizzi IP");
                            $IP->Position(false);
                            
                            $IP->Column("allowed_ips", "Ip", "text", null, 11);

                            echo $IP->Generate();

                        ?>
                    </wi-card>

                    <wi-card>
                        <div class="col-12">
                            <?php

                                $A = [];
                                $authority = null;

                                foreach (permissionsApi() as $key => $value) {
                                    if (count(array_intersect(permissions($key)->creator, $USER->authority)) >= 1) {
                                        $A[$key] = $value;
                                        if (isset($VALUES['authority'])) { 
                                            if (is_array(json_decode($VALUES['authority'], true)) && in_array($key, json_decode($VALUES['authority'], true))) {
                                                $authority = $key; 
                                            } elseif ($key == $VALUES['authority']) {
                                                $authority = $key; 
                                            }
                                        }
                                    }
                                }

                                echo select('Autorizzazione', 'authority', $A, 'old', 'required', $authority);
                                
                            ?>
                        </div>
                        <div class="col-12">
                            <?php

                                $checkbox = [
                                    'true' => "Abilitato",
                                    'false' => "Disabilitato",
                                ];

                                echo select('Stato', 'active', $checkbox, 'old', 'required');

                            ?>
                        </div>
                        <div class="col-12"> 
                            <?=submitAdd()?>
                        </div>
                    </wi-card>

                </div>
            </div>
        
        </div>
    </form>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>