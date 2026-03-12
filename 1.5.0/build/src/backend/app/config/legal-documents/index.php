<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";

    if (isset($_POST['content_snapshot'])) {
        $_POST['content_hash'] = hash('sha256', $_POST['content_snapshot']);
    }

    if (isset($_POST['doc_type']) && (!isset($_POST['name']) || trim((string) $_POST['name']) === '')) {
        $_POST['name'] = ucwords(str_replace([ '_', '-' ], ' ', (string) $_POST['doc_type']));
    }
    
    require_once $ROOT_APP."/html/backend/index.php";

    if (
        (!isset($VALUES['name']) || trim((string) $VALUES['name']) === '')
        && isset($VALUES['doc_type'])
    ) {
        $VALUES['name'] = ucwords(str_replace([ '_', '-' ], ' ', (string) $VALUES['doc_type']));
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
        <div class="row g-3">

            <wi-card class="col-12">
                <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$TITLE?></h3>
            </wi-card>


            <div class="col-12">
                <div class="row g-3">

                    <wi-card>
                        <div class="col-8">
                            <?=text('Nome', 'name', 'required'); ?>
                        </div>
                        <div class="col-4">
                            <?=select('Tipologia documento', 'doc_type', legalDocumentTypes(), null, 'required'); ?>
                        </div>
                        <div class="col-4">
                            <?=select('Lingua', 'language_code', array_map(fn($lang) => $lang['name'], __ls()), null, 'required'); ?>
                        </div>
                        <div class="col-4">
                            <?=text('Versione', 'version', 'required'); ?>
                        </div>
                        <div class="col-4">
                            <?php

                                $publishedAtDefault = isset($VALUES['published_at']) && strtotime((string) $VALUES['published_at']) !== false
                                    ? date('Y-m-d\TH:i', strtotime((string) $VALUES['published_at']))
                                    : date('Y-m-d\TH:i');

                                echo textDatetime('Pubblicato il', 'published_at', 'required', $publishedAtDefault);

                            ?>
                        </div>
                        <div class="col-12">
                            <?=textarea(
                                'Testo checkbox',
                                'checkbox_label',
                                'required',
                                'plus',
                                isset($VALUES['checkbox_label']) ? html_entity_decode((string) $VALUES['checkbox_label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null
                            ); ?>
                        </div>

                    </wi-card>

                    </wi-card>
                        <div class="col-12">
                            <?=textarea('Testo', 'content_snapshot', 'required', 'blog'); ?>
                        </div>
                    <wi-card>
                        
                </div>
            </div>

            <div class="col-3">
                <div class="row g-3">

                    <wi-card class="col-3">
                        <div class="col-12">
                            <?=select('Stato', 'active', [ 'true' => 'Attivo', 'false' => 'Non attivo' ], 'old', 'required'); ?>
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
