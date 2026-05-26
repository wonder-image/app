<?php

$ERROR = (int) ($_GET['errCode'] ?? 500);

require $ROOT_APP.'/view/error/http.php';
