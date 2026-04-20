<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    use Wonder\App\Table;
    use Wonder\App\Resources\Media\DocumentResource;
    use Wonder\App\Resources\Media\IconResource;
    use Wonder\App\Resources\Media\ImageResource;
    
    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Uploader massivo";
    $INFO_PAGE->table = "media";

    $NAME = (object) array();
    $NAME->table = "media_upload_massive";
    $NAME->folder = "media";

    $TABLE->MEDIA_UPLOAD_MASSIVE = [
        'file' => [
            'input' => [
                'format' => [
                    'max_file' => 10,
                    'max_size' => 5,
                ],
            ],
        ],
    ];

    if (isset($_POST['upload'])) {
        
        foreach ($_FILES['file']['name'] as $key => $fileName) {
            
            $fileName = pathinfo($_FILES['file']['name'][$key], PATHINFO_FILENAME);

            $resourceClass = match ($_POST['type']) {
                'image' => ImageResource::class,
                'icon' => IconResource::class,
                default => DocumentResource::class,
            };

            $NAME->folder = $resourceClass::legacyFolder();
                    
            $POST = [
                'type' => $_POST['type'],
                'name' => $fileName,
                'file' => [
                    'name' => [$_FILES['file']['name'][$key]],
                    'type' => [$_FILES['file']['type'][$key]],
                    'tmp_name' => [$_FILES['file']['tmp_name'][$key]],
                    'error' => [$_FILES['file']['error'][$key]],
                    'size' => [$_FILES['file']['size'][$key]],
                ]
            ];

            $POST = $resourceClass::mutateRequestValues($POST, 'store', 'backend');

            $VALUES = Table::key($resourceClass::prepareSchemaName())
                ->prepareFor($resourceClass::modelTable(), $POST);

            sqlInsert($resourceClass::modelTable(), $VALUES);
            
        }

        if (empty($ALERT)) { header("Location: ?alert=650"); }

    }

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$INFO_PAGE->title?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>
    
    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <form action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">

        <div class="row g-3">

            <wi-card class="col-12">
                <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$INFO_PAGE->title?></h3>
            </wi-card>

            <div class="col-9">
                <div class="row g-3">

                    <wi-card class="col-12">
                        <div class="col-12">
                            <?=inputFileDragDrop('', 'file', 'classic')?>
                        </div>
                    </wi-card>

                </div>
            </div>

            <wi-card class="col-3">
                <div class="col-12">
                    <?=select('Tipologia', 'type', [ 'image' => 'Immagine', 'icon' => 'Icona', 'document' => 'Documento' ])?>
                </div>
                <div class="col-12">
                    <?=submit()?>
                </div>
            </wi-card>
        
        </div>
    </form>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>


</body>
</html>
