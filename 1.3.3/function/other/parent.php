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

    function parentMain($table, $childId) {

        $SQL = sqlSelect($table, [ 'id' => $childId, 'deleted' => 'false' ],1);

        if ($SQL->row['parent_id'] == '0') {
            return $SQL->id;
        } else {
            return parentMain($table, $SQL->row['parent_id']);
        }

    }

    function parentTree($table, $parentId, $tree = 'all') {

        $RETURN = (object) array();

        $RETURN->array = [];
        $RETURN->badge = "";

        if (is_array(json_decode($parentId, true)) || is_array($parentId)) {

            $parentId = is_array($parentId) ? $parentId : json_decode($parentId, true);
            $mainId = [];

            foreach ($parentId as $key => $pId) {

                if ($pId != '0') {
                    if ($tree == 'all') {

                        $SQL = sqlSelect($table, [ 'id' => $pId, 'deleted' => 'false' ]);

                        $RETURN->badge .= parentTree($table, $pId, $tree)->badge.'<br>';

                    } elseif ($tree == 'main') {

                        $parentMain = parentMain($table, $pId);

                        if (!in_array($parentMain, $mainId)) {
                            array_push($mainId, $parentMain);
                            $RETURN->badge .= parentTree($table, $parentMain, $tree)->badge.'<br>';
                        }

                    }
                }

            }

            $RETURN->badge = substr($RETURN->badge, 0, -4);

        } else {

            if ($parentId != '0') {

                $SQL = sqlSelect($table, [ 'id' => $parentId, 'deleted' => 'false' ]);

                foreach ($SQL->row as $key => $row) {

                    if ($tree == 'all') {
                        
                        if ($row['parent_id'] != '0') {
                            
                            $RETURN->array = parentTree($table, $row['parent_id'], $tree)->array;
                            $RETURN->badge .= parentTree($table, $row['parent_id'], $tree)->badge;

                        }

                        array_push($RETURN->array, $row['id']);
                        $RETURN->badge .= '<span class="badge bg-secondary">'.strtoupper($row['name']).'</span> ';

                    } elseif ($tree == 'main') {

                        if ($row['parent_id'] == '0') {
                            $RETURN->badge .= '<span class="badge bg-secondary">'.strtoupper($row['name']).'</span> ';
                        } else {
                            $RETURN->badge .= parentTree($table, $row['parent_id'], $tree)->badge;
                        }

                    }

                }

            }

        }

        return $RETURN;

    }

?>