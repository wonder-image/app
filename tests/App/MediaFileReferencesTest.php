<?php
declare(strict_types=1);

require dirname(__DIR__).'/harness.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/app/function/file/upload.php';
require dirname(__DIR__, 2).'/app/function/sql.php';

$directory = sys_get_temp_dir().'/wonder-file-references-'.bin2hex(random_bytes(6));
mkdir($directory);
$format = ['file' => true, 'max_file' => 3, 'reset' => true];
$old = ['a.png', 'b.png'];
$empty = ['name' => [], 'tmp_name' => [], 'error' => []];
foreach ($old as $file) {
    file_put_contents($directory.'/'.$file, $file);
    touch($directory.'/'.$file, 1600000000);
}

try {
    check('Bootstrap renders stored arrays and enables reference persistence', function () {
        $element = (new Wonder\Elements\Form\Components\File('photos'))->fileValue(['a.png', 'b.png']);
        $html = $element->render('bootstrap');
        return str_contains($html, 'data-wi-file-references="true"')
            && str_contains($html, 'data-wi-value="[&quot;a.png&quot;,&quot;b.png&quot;]"');
    });

    foreach ([$old, array_reverse($old)] as $order) {
        check('Existing files retain names, bytes and timestamps in requested order: '.implode(',', $order), function () use ($directory, $format, $old, $empty, $order) {
            $GLOBALS['ALERT'] = null;
            $result = uploadFiles($empty, $format, $directory, $old, json_encode($order));
            foreach ($old as $file) {
                if (file_get_contents($directory.'/'.$file) !== $file || filemtime($directory.'/'.$file) !== 1600000000) { return false; }
            }
            return json_decode($result, true) === $order && empty($GLOBALS['ALERT']);
        });
    }

    foreach (['invalid', '{}', '["foreign.png"]', '["a.png","a.png"]', '[0]', '["a.png","b.png",0,1]'] as $manifest) {
        check('Invalid manifest preserves existing files: '.$manifest, function () use ($directory, $format, $old, $empty, $manifest) {
            $GLOBALS['ALERT'] = null;
            $result = uploadFiles($empty, $format, $directory, $old, $manifest);
            return json_decode($result, true) === $old && !empty($GLOBALS['ALERT'])
                && is_file($directory.'/a.png') && is_file($directory.'/b.png');
        });
    }

    check('Form persistence accepts a manifest without multipart files', function () use ($directory, $format, $old) {
        $GLOBALS['ALERT'] = null;
        $GLOBALS['NAME'] = (object) ['folder' => ''];
        $GLOBALS['PATH'] = (object) ['rUpload' => $directory];
        $result = formToArray('test', ['photos__wi_files' => '["b.png","a.png"]'],
            ['photos' => ['input' => ['format' => $format]]], ['id' => 1, 'photos' => json_encode($old)]);
        return json_decode($result['photos'], true) === ['b.png', 'a.png'];
    });

    check('Failed upload does not remove retained files', function () use ($directory, $format, $old) {
        $GLOBALS['ALERT'] = null;
        $files = ['name' => ['new.png'], 'tmp_name' => [''], 'error' => [UPLOAD_ERR_INI_SIZE]];
        return json_decode(uploadFiles($files, $format, $directory, $old, '["b.png",0]'), true) === $old
            && !empty($GLOBALS['ALERT']) && is_file($directory.'/a.png') && is_file($directory.'/b.png');
    });

    check('No manifest and no upload preserves legacy values', function () use ($directory, $format, $old, $empty) {
        $GLOBALS['ALERT'] = null;
        return json_decode(uploadFiles($empty, $format, $directory, $old), true) === $old;
    });

    check('Explicit removal deletes only the removed file', function () use ($directory, $format, $old, $empty) {
        $GLOBALS['ALERT'] = null;
        $result = uploadFiles($empty, $format, $directory, $old, '["b.png"]');
        return json_decode($result, true) === ['b.png'] && !is_file($directory.'/a.png') && is_file($directory.'/b.png');
    });

    check('Empty manifest removes all files', function () use ($directory, $format, $empty) {
        $GLOBALS['ALERT'] = null;
        return json_decode(uploadFiles($empty, $format, $directory, ['b.png'], '[]'), true) === []
            && !is_file($directory.'/b.png');
    });
} finally {
    foreach (glob($directory.'/*') as $file) { unlink($file); }
    rmdir($directory);
}

summary();
