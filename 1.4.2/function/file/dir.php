<?php

    function copyDir(
        string $sourceDirectory,
        string $destinationDirectory,
        string $childFolder = ''
    ): void {

        $directory = opendir($sourceDirectory);

        if (is_dir($destinationDirectory) === false) {
            mkdir($destinationDirectory);
        }

        if ($childFolder !== '') {
            if (is_dir("$destinationDirectory/$childFolder") === false) {
                mkdir("$destinationDirectory/$childFolder");
            }

            while (($file = readdir($directory)) !== false) {
                if ($file != '' && $file === '.' || $file === '..') {
                    continue;
                }

                if (is_dir("$sourceDirectory/$file") === true) {
                    copyDir("$sourceDirectory/$file", "$destinationDirectory/$childFolder/$file");
                } else {
                    copy("$sourceDirectory/$file", "$destinationDirectory/$childFolder/$file");
                }
            }

            closedir($directory);

            return;
        }

        while (($file = readdir($directory)) !== false) {
            if ($file != '' && $file === '.' || $file === '..') {
                continue;
            }

            if (is_dir("$sourceDirectory/$file") === true) {
                copyDir("$sourceDirectory/$file", "$destinationDirectory/$file");
            }
            else {
                copy("$sourceDirectory/$file", "$destinationDirectory/$file");
            }
        }

        closedir($directory);

    }

    function deleteDir($dir) {

        if (is_dir($dir)) {

            $files = scandir($dir);

            foreach ($files as $file) {
               if ($file != '' && $file !== '.' && $file !== '..') {
                    $filePath = $dir.'/'.$file;
                    if (is_dir($filePath)) {
                        deleteDir($filePath);
                    } else {
                        unlink($filePath);
                    }
               }
            }
            
            rmdir($dir);

        }

        clearstatcache();

    }

    function scanParentDir( string $dir, bool $childArray = false ) {

        $files = empty(scandir($dir)) ? [] : scandir($dir);

        $fileArray = [];

        foreach ($files as $file) {
            if ($file != '' && $file != '.' && $file != '..') {
                
                if (isset(pathinfo($file)['extension'])) {

                    array_push($fileArray, $file);

                } else {

                    if ($childArray) {
                        $fileArray[$file] = [];
                    }

                    foreach (scanParentDir($dir.'/'.$file) as $subFile) {
                        if ($childArray) {
                            array_push($fileArray[$file], $subFile);
                        } else {
                            array_push($fileArray, $file.'/'.$subFile);
                        }
                    }
                    
                }
    
            }
        }

        return $fileArray;

    }