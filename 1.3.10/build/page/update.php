<?php

    if (is_dir($ROOT.'/assets/upload/user/') === false) { mkdir($ROOT.'/assets/upload/user/'); }
    if (is_dir($ROOT.'/assets/upload/user/profile-picture/') === false) { mkdir($ROOT.'/assets/upload/user/profile-picture/'); }

    if (file_exists($ROOT.'/update/page/index.php')) { deleteDir($ROOT.'/update/'); }

    copyDir($ROOT_APP.'/src/update/', $ROOT.'/update/');