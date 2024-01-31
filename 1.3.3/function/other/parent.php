<?php

    function childTree($table, $parentId = '0', $removeId = null) {

        $RETURN = [];

        $SQL = sqlSelect($table, [ 'parent_id' => $parentId, 'deleted' => 'false' ]);

        foreach ($SQL->row as $key => $row) {

            $rowId = $row['id'];
            $rowName = $row['name'];

            if ($rowId != $removeId) {

                $RETURN[$rowId] = [];
                $RETURN[$rowId]['name'] = $rowName;
                $RETURN[$rowId]['child'] = childTree($table, $rowId, $removeId);
                
            }
            
        }

        return $RETURN;

    }

    function parentTree($table, $parentId) {

        $RETURN = (object) array();

        $RETURN->badge = "";

        if (is_array(json_decode($parentId, true))) {
            
            $parentId = json_decode($parentId, true);

            foreach ($parentId as $key => $pId) {

                $SQL = sqlSelect($table, [ 'id' => $pId, 'parent_id' => '0', 'deleted' => 'false' ]);

                foreach ($SQL->row as $key => $row) {
                    $RETURN->badge .= parentTree($table, $pId)->badge;
                }

            }

        } else {

            $SQL = sqlSelect($table, [ 'id' => $parentId, 'deleted' => 'false' ]);

            foreach ($SQL->row as $key => $row) {
                
                if ($row['parent_id'] != '0') {
                    $RETURN->badge .= parentTree($table, $row['parent_id'])->badge;
                } 

                $RETURN->badge .= '<span class="badge bg-secondary">'.$row['name'].'</span> ';

            }

        }

        return $RETURN;

    }

?>