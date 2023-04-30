<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if ($_POST['post']) {

        $id = $_GET['id'];
        $table = $_GET['table'];
        $area = isset($_GET['area']) ? $_GET['area'] : null;
        $authority = isset($_GET['authority']) ? $_GET['authority'] : null;

        $SQL = sqlSelect($table, ['id' => $id], 1);

        $USER_AREA = json_decode($SQL->row['area'], true);
        $USER_AUTHORITY = json_decode($SQL->row['authority'], true);

        $VALUES = [];

        if ($area != null) {

            if (count($USER_AREA) == 1) {
                $AREA = [];
            } else {
                $AREA = [];
                foreach ($USER_AREA as $key => $v) {
                    if ($v != 'area') {
                        array_push($AREA, $v);
                    }
                }
            }

            $VALUES['area'] = json_encode($AREA);

        }

        if ($authority != null) {

            if (count($USER_AUTHORITY) == 1) {
                $AUTHORITY = [];
            } else {
                $AUTHORITY = [];
                foreach ($USER_AUTHORITY as $key => $v) {
                    if ($v != 'authority') {
                        array_push($AUTHORITY, $v);
                    }
                }
            }

            $VALUES['authority'] = json_encode($AUTHORITY);

        }

        if ($area == 'backend' && $authority == null) {
           
            if (count($USER_AUTHORITY) == 1) {
                $AUTHORITY = [];
            } else {
                $AUTHORITY = [];
                foreach ($USER_AUTHORITY as $key => $v) {
                    if (permissions($v)->area != 'backend') {
                        array_push($AUTHORITY, $v);
                    }
                }
            }

            $VALUES['authority'] = json_encode($AUTHORITY);

        }

        $sql = sqlModify($table, $VALUES, 'id', $id);

    }

?>