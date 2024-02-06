<?php

    function uploadFiles($FILES, $FORMAT, $PATH_DIR, $OLD_FILE = []) {

        global $ALERT;
        
        $MAX_FILE = isset($FORMAT['max_file']) ? $FORMAT['max_file'] : 1;
        $MAX_SIZE = isset($FORMAT['max_size']) ? $FORMAT['max_size'] * 1048576 : 2 * 1048576;
        $EXTENSIONS = isset($FORMAT['extensions']) ? $FORMAT['extensions'] : '';
        $DIR = isset($FORMAT['dir']) ? $FORMAT['dir'] : '/';
        $RESIZE = isset($FORMAT['resize']) ? $FORMAT['resize'] : '';
        $RESET = isset($FORMAT['reset']) ? $FORMAT['reset'] : false;

        $NEW_FILE = ($RESET == true) ? [] : $OLD_FILE;
        $N_OLD_FILE = count($NEW_FILE);

        $N_NEW_FILE = count($FILES['name']);
        $N_FILE = $N_NEW_FILE + $N_OLD_FILE;

        if ($N_FILE <= $MAX_FILE) {
            
            for ($i=0; $i < $N_NEW_FILE; $i++) { 

                $TEMPORARY = $FILES['tmp_name'][$i];

                if (!empty($TEMPORARY)) {

                    $FILE_NAME = strtolower($FILES['name'][$i]);
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

                            if (!empty($RESIZE)) {
                                if (isset($RESIZE[0]) && is_array($RESIZE[0])) {
                                    foreach ($RESIZE as $k => $v) {

                                        $WIDTH = $v['width'];
                                        $HEIGHT = $v['height'];
                                        
                                        resizeImage($NEW_PATH, $v['width'], $v['height'], $PATH_UPLOAD, $WIDTH.'x'.$HEIGHT.'-'.$NEW_NAME);

                                    }
                                } else {

                                    $WIDTH = $RESIZE['width'];
                                    $HEIGHT = $RESIZE['height'];

                                    resizeImage($NEW_PATH, $RESIZE['width'], $HEIGHT, $PATH_UPLOAD, $WIDTH.'x'.$HEIGHT.'-'.$NEW_NAME);

                                }
                            }

                        } else {

                            $ALERT = 920;
                            
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

?>