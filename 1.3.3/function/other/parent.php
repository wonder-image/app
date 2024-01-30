<?php

    function parentTree($table, $parentId = '0', $removeId = null) {

        $RETURN = [];

        $SQL = sqlSelect($table, [ 'parent_id' => $parentId, 'deleted' => 'false' ]);

        foreach ($SQL->row as $key => $row) {

            $rowId = $row['id'];
            $rowName = $row['name'];

            if ($rowId != $removeId) {

                $RETURN[$rowId] = [];
                $RETURN[$rowId]['name'] = $rowName;
                $RETURN[$rowId]['child'] = parentTree($table, $rowId, $removeId);
                
            }
            
        }

        return $RETURN;

    }

?>