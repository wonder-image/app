<?php

    function uploadFiles($FILES, $FORMAT, $PATH_DIR, $OLD_FILE = []) {

        global $ALERT;

        $OLD_FILE = \Wonder\App\Support\MediaFileManager::decodeStoredFiles($OLD_FILE);
        
        $MAX_FILE = $FORMAT['max_file'] ?? 1;
        $MAX_SIZE = isset($FORMAT['max_size']) ? $FORMAT['max_size'] * 1048576 : 2 * 1048576;
        $EXTENSIONS = $FORMAT['extensions'] ?? '';
        $DIR = $FORMAT['dir'] ?? '/';
        $CUSTOM_NAME = $FORMAT['name'] ?? '';
        $RESIZE = $FORMAT['resize'] ?? [];
        $WEBP = $FORMAT['webp'] ?? false;
        $RESET = $FORMAT['reset'] ?? false;

        if ($RESIZE === true) {
            $RESIZE = null;
        } else if (isset($RESIZE['width'])) {
            $RESIZE = [ $RESIZE['width'] ];
        } else if (isset($RESIZE[0]['width'])) {

            $R = [];

            foreach ($RESIZE as $key => $value) { array_push($R, $value['width']); }

            $RESIZE = $R;

        }

        if (!\Wonder\App\Support\MediaFileManager::hasUploadedFiles($FILES)) {
            return json_encode($OLD_FILE, JSON_PRETTY_PRINT);
        }

        $NEW_FILE = ($RESET == true) ? [] : (empty($OLD_FILE) ? [] : $OLD_FILE);
        $N_OLD_FILE = count($NEW_FILE);

        $N_NEW_FILE = 0;

        foreach ((array) ($FILES['tmp_name'] ?? []) as $temporary) {
            if (is_string($temporary) && trim($temporary) !== '') {
                $N_NEW_FILE++;
            }
        }

        $REPLACE_SINGLE_FILE = !$RESET
            && $MAX_FILE === 1
            && $N_NEW_FILE > 0
            && !empty($OLD_FILE);

        if ($REPLACE_SINGLE_FILE) {
            $RESET = true;
        }

        $N_FILE = $RESET ? $N_NEW_FILE : ($N_NEW_FILE + $N_OLD_FILE);
        $REMOVED_OLD_FILE = false;

        if ($N_FILE <= $MAX_FILE) {
            
            $TOTAL_FILE = count((array) ($FILES['name'] ?? []));

            for ($i=0; $i < $TOTAL_FILE; $i++) {

                $TEMPORARY = $FILES['tmp_name'][$i] ?? '';

                if (!empty($TEMPORARY)) {

                    $FILE_NAME = strtolower((string) ($FILES['name'][$i] ?? ''));
                    $EXTENSION = pathinfo($FILE_NAME, PATHINFO_EXTENSION);
                    $SIZE = (int) ($FILES['size'][$i] ?? 0);

                    if (!empty($EXTENSIONS) && !in_array($EXTENSION, $EXTENSIONS)) { $ALERT = 921; }
                    if (empty($ALERT) && $SIZE >= $MAX_SIZE) { $ALERT = 922; }

                    if (empty($ALERT)) {

                        if (substr($DIR, -1) != '/') {

                            $DIR_PARTS = explode('/', $DIR);
                            $NEW_NAME = array_pop($DIR_PARTS);
                            $PATH_UPLOAD = $PATH_DIR.implode('/', $DIR_PARTS);

                            if (substr($PATH_UPLOAD, -1) != '/') { $PATH_UPLOAD .= '/'; }

                        } else {

                            $NEW_NAME = code(10, 'all');
                            $PATH_UPLOAD = $PATH_DIR.$DIR;

                        }

                        if (!empty($CUSTOM_NAME)) {

                            $NEW_NAME = $CUSTOM_NAME;

                            if (strpos($NEW_NAME, '{rand}') !== false) {
                                $NEW_NAME = str_replace('{rand}', code(3, 'letter'), $NEW_NAME);
                            }

                            $NEW_NAME = create_link(strtolower($NEW_NAME));

                        }

                        if (!is_dir($PATH_UPLOAD) && !@mkdir($PATH_UPLOAD, 0775, true) && !is_dir($PATH_UPLOAD)) {
                            $ALERT = 920;
                        }

                        if (empty($ALERT) && $RESET == true && $REMOVED_OLD_FILE == false && !empty($OLD_FILE)) {
                            \Wonder\App\Support\MediaFileManager::deleteFiles($PATH_DIR, $FORMAT, $OLD_FILE);
                            $REMOVED_OLD_FILE = true;
                        }

                        $NEW_PATH = $PATH_UPLOAD.$NEW_NAME.'.'.$EXTENSION;

                        if (empty($ALERT) && move_uploaded_file($TEMPORARY, $NEW_PATH)) {

                            $NEW_FILE[$N_OLD_FILE] = $NEW_NAME.'.'.$EXTENSION;

                            if ((!empty($RESIZE) || $WEBP) && in_array($EXTENSION, ['jpg', 'jpeg', 'png', 'webp'])) {
                                imageResize($NEW_PATH, $RESIZE, $WEBP);
                            }

                        } else if (empty($ALERT)) {

                            $ALERT = 920;

                        }

                    }

                }

                $N_OLD_FILE++;

            }
            
        } else {

            $ALERT = 923;

        }

        return json_encode($NEW_FILE, JSON_PRETTY_PRINT);
    
    }
