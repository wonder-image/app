<?php

    foreach ([
        $ROOT.'/assets/upload/user/profile-picture/',
        $ROOT.'/handler/',
        $ROOT.'/api/',
        $ROOT.'/backend/',
    ] as $directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    if (file_exists($ROOT.'/update/page/index.php')) {
        deleteDir($ROOT.'/update/');
    }

    // Pulisce gli endpoint legacy sostituiti dal router.
    if (is_dir($ROOT.'/api/app/update/')) {
        deleteDir($ROOT.'/api/app/update/');
    }

    if (file_exists($ROOT.'/api/task/sitemap.php')) {
        unlink($ROOT.'/api/task/sitemap.php');
    }

    if (is_dir($ROOT.'/backend/account/')) {
        deleteDir($ROOT.'/backend/account/');
    }

    $HANDLER_INDEX = <<<'PHP'
<?php

    $ROOT = dirname(__DIR__);
    require_once $ROOT.'/vendor/autoload.php';

    (new \Wonder\Http\RouteDispatcher($ROOT))->handleRequest();
PHP;

    file_put_contents($ROOT.'/handler/index.php', $HANDLER_INDEX);
