<?php

    function filesJsonToArray($pathFiles) 
    {

        $result = [];

        if (!is_dir($pathFiles)) {
            throw new RuntimeException("Path not found: {$pathFiles}");
        }

        // Trova tutti i file JSON ricorsivamente, ordinati per data di modifica crescente
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pathFiles, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                $files[] = $file->getPathname();
            }
        }

        usort($files, function($a, $b) {
            return filemtime($a) <=> filemtime($b);
        });

        foreach ($files as $filePath) {
            // Calcola la chiave principale come percorso relativo dal path base senza estensione .json
            $relativePath = substr($filePath, strlen($pathFiles));
            $relativePath = preg_replace('/\.json$/', '', $relativePath);
            $keys = explode(DIRECTORY_SEPARATOR, $relativePath);

            $content = json_decode(file_get_contents($filePath), true);
            if (is_array($content)) {
                // Inserisce il contenuto sotto la chiave principale
                $ref = &$result;
                foreach ($keys as $key) {
                    if (!isset($ref[$key]) || !is_array($ref[$key])) {
                        $ref[$key] = [];
                    }
                    $ref = &$ref[$key];
                }
                // Sovrascrive solo i valori ridichiarati
                $ref = arrayMergeDeclared($ref, $content);
                unset($ref);
            }
        }

        return $result;

    }