<?php

    function childTree($table, $parentId = '0', $removeId = null, $colName = 'name') {

        $RETURN = [];

        $SQL = sqlSelect($table, [ 'parent_id' => $parentId, 'deleted' => 'false' ]);

        foreach ($SQL->row as $key => $row) {

            $rowId = $row['id'];
            $rowName = $row[$colName];

            if ($rowId != $removeId) {

                $RETURN[$rowId] = [];
                $RETURN[$rowId]['name'] = $rowName;
                $RETURN[$rowId]['child'] = childTree($table, $rowId, $removeId, $colName);
                
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
        $RETURN->text = "";

        if (is_array(json_decode($parentId, true)) || is_array($parentId)) {

            $parentId = is_array($parentId) ? $parentId : json_decode($parentId, true);
            $mainId = [];

            foreach ($parentId as $key => $pId) {

                if ($pId != '0') {
                    if ($tree == 'all') {

                        $SQL = sqlSelect($table, [ 'id' => $pId, 'deleted' => 'false' ]);

                        $PARENT_TREE = parentTree($table, $pId, $tree);

                        $RETURN->badge .= $PARENT_TREE->badge.'<br>';
                        $RETURN->text .= $PARENT_TREE->text.'<br>';

                    } elseif ($tree == 'main') {

                        $parentMain = parentMain($table, $pId);

                        if (!in_array($parentMain, $mainId)) {
                            array_push($mainId, $parentMain);

                            $PARENT_TREE = parentTree($table, $parentMain, $tree);

                            $RETURN->badge .= $PARENT_TREE->badge.'<br>';
                            $RETURN->text .= $PARENT_TREE->text.'<br>';

                        }

                    }
                }

            }

            $RETURN->badge = substr($RETURN->badge, 0, -4);
            $RETURN->text = substr($RETURN->text, 0, -4);

        } else {

            if ($parentId != '0') {

                $SQL = sqlSelect($table, [ 'id' => $parentId, 'deleted' => 'false' ], 1);

                $row = $SQL->row;

                if ($tree == 'all') {
                    
                    if ($row['parent_id'] != '0') {
                        
                        $PARENT_TREE = parentTree($table, $row['parent_id'], $tree);

                        $RETURN->array = $PARENT_TREE->array;
                        $RETURN->badge .= $PARENT_TREE->badge;
                        $RETURN->text .= $PARENT_TREE->text.' - ';

                    }

                    array_push($RETURN->array, $row['id']);

                    $RETURN->badge .= '<span class="badge bg-secondary">'.strtoupper($row['name']).'</span> ';
                    $RETURN->text .= $row['name'];

                } elseif ($tree == 'main') {

                    if ($row['parent_id'] == '0') {

                        $RETURN->badge .= '<span class="badge bg-secondary">'.strtoupper($row['name']).'</span> ';
                        $RETURN->text .= $row['name'].' - ';

                    } else {

                        $PARENT_TREE = parentTree($table, $row['parent_id'], $tree);

                        $RETURN->badge .= $PARENT_TREE->badge;
                        $RETURN->text .= $PARENT_TREE->text;

                    }

                }

            }

        }

        return $RETURN;

    }