<?php

    $PRIVATE = false;
    $FRONTEND = true;
    $ROOT = $_SERVER['DOCUMENT_ROOT'];

    include $ROOT.'/app/wonder-image.php';

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <?php 
        
        $SEO->title = "Demo alert";
        $SEO->description = "";

        include $ROOT_APP.'/utility/frontend/head.php';
        
    ?>

</head>
<body>

    <?php include $ROOT_APP.'/utility/frontend/body-start.php' ?>

    <section>
        <div class="content">
            <div class="d-grid gap-4 w-100">
                <div class="title-big">
                    Alert
                </div>
                <div class="d-grid col-1 gap-5">

                </div>
                <?php

                    foreach ($ALERT_CODE as $key => $value) {
                        echo "
                        <div class='w-100'>
                            <div class='subtitle tx-{$value['type']}'>
                                <b>$key</b> => {$value['title']}
                            </div>
                            <div class='text'>
                                {$value['text']}
                            </div>
                        </div>
                        ";
                    }

                ?>
            </div>
        </div>
    </section>

    <?php include $ROOT_APP.'/utility/frontend/body-end.php' ?>
    
</body>
</html>