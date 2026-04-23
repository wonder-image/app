<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\PageSchema\UploadMassivePageSchema;
use Wonder\App\Table;
use Wonder\App\Resources\Media\DocumentResource;
use Wonder\App\Resources\Media\IconResource;
use Wonder\App\Resources\Media\ImageResource;
use Wonder\View\View;

$TITLE = 'Uploader massivo';

Table::key('media_upload_massive')->setSchema([
    'file' => [
        'input' => [
            'format' => [
                'max_file' => 10,
                'max_size' => 5,
            ],
        ],
    ],
]);

$NAME = (object) [
    'table' => 'media_upload_massive',
    'folder' => 'media',
];

LegacyGlobals::set('NAME', $NAME);

$FORM_SCHEMA = UploadMassivePageSchema::formSchema();

if (isset($_POST['upload']) && isset($_FILES['file']) && is_array($_FILES['file']['name'] ?? null)) {
    foreach ($_FILES['file']['name'] as $key => $fileName) {
        $fileName = pathinfo((string) $fileName, PATHINFO_FILENAME);

        $resourceClass = match ((string) ($_POST['type'] ?? 'document')) {
            'image' => ImageResource::class,
            'icon' => IconResource::class,
            default => DocumentResource::class,
        };

        LegacyGlobals::set('NAME', (object) [
            'table' => 'media_upload_massive',
            'folder' => $resourceClass::legacyFolder(),
        ]);

        $post = [
            'type' => (string) ($_POST['type'] ?? 'document'),
            'name' => $fileName,
            'file' => [
                'name' => [$_FILES['file']['name'][$key]],
                'type' => [$_FILES['file']['type'][$key]],
                'tmp_name' => [$_FILES['file']['tmp_name'][$key]],
                'error' => [$_FILES['file']['error'][$key]],
                'size' => [$_FILES['file']['size'][$key]],
            ],
        ];

        $post = $resourceClass::mutateRequestValues($post, 'store', 'backend');

        $values = Table::key($resourceClass::prepareSchemaName())
            ->prepareFor($resourceClass::modelTable(), $post);

        sqlInsert($resourceClass::modelTable(), $values);
    }

    if (empty($ALERT)) {
        header('Location: '.__r('backend.media.upload-massive').'?alert=650');
        exit;
    }
}

LegacyGlobals::set('NAME', $NAME);

View::make($ROOT_APP.'/view/pages/backend/media/upload-massive.php', [
    'TITLE' => $TITLE,
    'USER' => is_object($USER ?? null) ? $USER : (object) [ 'authority' => [] ],
    'FORM_SCHEMA' => $FORM_SCHEMA,
])->render();
