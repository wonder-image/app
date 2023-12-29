<?php

    if (file_exists($ROOT.'/update/page/index.php')) { deleteDir($ROOT.'/update/'); }

    copyDir($ROOT_APP.'/src/update/', $ROOT.'/update/');

?>