<?php

    $PRIVATE = false;

    if (isset($_POST['backend'])) { $BACKEND = true; }
    if (isset($_POST['frontend'])) { $FRONTEND = true; }

    $ROOT = $_SERVER['DOCUMENT_ROOT'].'/';

    include $ROOT.'app/wonder-image.php';

    if ($_POST['post']) {
        echo alertTheme($_POST['alert']);
    }

?>