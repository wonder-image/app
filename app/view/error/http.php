<?php

$ERROR = (int) ($ERROR ?? ($_GET['errCode'] ?? 500));
$ERROR_MESSAGE = trim((string) ($ERROR_MESSAGE ?? ''));
$ERROR_FILE = trim((string) ($ERROR_FILE ?? ''));
$ERROR_LINE = (int) ($ERROR_LINE ?? 0);
$ERROR_TRACE = trim((string) ($ERROR_TRACE ?? ''));
$ERROR_ASSETS = '';

if (isset($PATH) && is_object($PATH) && isset($PATH->appAssets)) {
    $ERROR_ASSETS = trim((string) $PATH->appAssets);
}

if ($ERROR_ASSETS === '') {
    $rootApp = rtrim((string) ($ROOT_APP ?? ''), '/');

    if ($rootApp !== '' && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $documentRoot = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/');

        if (str_starts_with($rootApp, $documentRoot)) {
            $ERROR_ASSETS = substr($rootApp, strlen($documentRoot)).'/assets';
        }
    }
}

if ($ERROR_ASSETS === '') {
    $ERROR_ASSETS = '/vendor/wonder-image/app/app/assets';
}

http_response_code($ERROR);

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Errore <?=$ERROR?></title>

    <link rel="stylesheet" href="<?=htmlspecialchars($ERROR_ASSETS, ENT_QUOTES, 'UTF-8')?>/css/error.css">

</head>
<body>

    <div id="contenitore">
        <div class="titolo">
            ERRORE <?=$ERROR?>
        </div>
        <div class="testo">
            Oooppsss un errore, stiamo investigando... <br>
            Continua la tua navigazione
        </div>
        <?php if ($ERROR_MESSAGE !== '') { ?>
        <div class="testo" style="text-align:left; max-width:900px; margin:24px auto 0; padding:16px 20px; border:1px solid rgba(255,255,255,.25); border-radius:12px; background:rgba(0,0,0,.15);">
            <div style="font-weight:700; margin-bottom:10px;">Debug locale</div>
            <div><strong>Messaggio:</strong> <?=htmlspecialchars($ERROR_MESSAGE, ENT_QUOTES, 'UTF-8')?></div>
            <?php if ($ERROR_FILE !== '') { ?>
            <div style="margin-top:8px;"><strong>File:</strong> <?=htmlspecialchars($ERROR_FILE, ENT_QUOTES, 'UTF-8')?><?php if ($ERROR_LINE > 0) { ?>:<?=$ERROR_LINE?><?php } ?></div>
            <?php } ?>
            <?php if ($ERROR_TRACE !== '') { ?>
            <details style="margin-top:12px;">
                <summary style="cursor:pointer;">Trace</summary>
                <pre style="white-space:pre-wrap; word-break:break-word; margin:10px 0 0;"><?=htmlspecialchars($ERROR_TRACE, ENT_QUOTES, 'UTF-8')?></pre>
            </details>
            <?php } ?>
        </div>
        <?php } ?>
        <a href ="<?=__u()?>">
            <div class="btn">
                TORNA AL SITO
            </div>
        </a>
        <div id="background"></div>
    </div>

</body>
</html>
