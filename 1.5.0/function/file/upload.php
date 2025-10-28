<?php

    function uploadFiles($FILES, $FORMAT, $PATH_DIR, $OLD_FILE = []) {

        global $ALERT;
        
        $MAX_FILE = $FORMAT['max_file'] ?? 1;
        $MAX_SIZE = isset($FORMAT['max_size']) ? $FORMAT['max_size'] * 1048576 : 2 * 1048576;
        $EXTENSIONS = $FORMAT['extensions'] ?? '';
        $DIR = $FORMAT['dir'] ?? '/';
        $RESIZE = $FORMAT['resize'] ?? [];
        $WEBP = $FORMAT['webp'] ?? false;
        $RESET = $FORMAT['reset'] ?? false;

        if ($RESIZE === true) {
            $RESIZE = null;
        } else if (isset($RESIZE['width'])) {
            $RESIZE = [ $RESIZE['width'] ];
        } else if (isset($RESIZE[0]['width'])) {

            $R = [];

            foreach ($RESIZE as $key => $value) {
                array_push($R, $value['width']);
            }

        }

        $NEW_FILE = ($RESET == true) ? [] : (empty($OLD_FILE) ? [] : $OLD_FILE);
        $N_OLD_FILE = count($NEW_FILE);

        $N_NEW_FILE = count($FILES['name']);
        $N_FILE = $N_NEW_FILE + $N_OLD_FILE;

        if ($N_FILE <= $MAX_FILE) {
            
            for ($i=0; $i < $N_NEW_FILE; $i++) { 

                $TEMPORARY = $FILES['tmp_name'][$i];

                if (!empty($TEMPORARY)) {

                    $FILE_NAME = $FILES['name'][$i];

                    if (in_array($FILE_NAME, $OLD_FILE)) {

                        # Se il file è già stato caricato non ricaricarlo
                        $NEW_FILE[$N_OLD_FILE] = $FILE_NAME;

                    } else {

                        $FILE_NAME = strtolower($FILE_NAME);
                        $EXTENSION = pathinfo($FILE_NAME, PATHINFO_EXTENSION);
                        $SIZE = $FILES['size'][$i];

                        if (!empty($EXTENSIONS) && !in_array($EXTENSION, $EXTENSIONS)) { $ALERT = 921; }
                        if (empty($ALERT) && $SIZE >= $MAX_SIZE) { $ALERT = 922; }

                        if (empty($ALERT)) {

                            if (substr($DIR, -1) != '/') {
                                
                                $NEW_NAME = explode('/', $DIR);
                                $lastKey = array_key_last($NEW_NAME);

                                $PATH_UPLOAD = $PATH_DIR.str_replace($NEW_NAME,'', $DIR);
                                $NEW_NAME = $NEW_NAME[$lastKey];

                            } else {

                                $NEW_NAME = code(10, 'all');
                                $PATH_UPLOAD = $PATH_DIR.$DIR;

                            }

                            $NEW_PATH = $PATH_UPLOAD.$NEW_NAME.'.'.$EXTENSION;
                            
                            if (move_uploaded_file($TEMPORARY, $NEW_PATH)) {

                                $NEW_FILE[$N_OLD_FILE] = $NEW_NAME.'.'.$EXTENSION;

                                imageResize($NEW_PATH, $RESIZE, $WEBP);

                            } else {

                                $ALERT = 920;
                                
                            }
                        
                        }

                    }

                }

                $N_OLD_FILE++;

            }
            
        } else {

            $ALERT = 923;

        }

        return json_encode($NEW_FILE);
    
    }
