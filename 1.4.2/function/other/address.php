<?php

    function createAddress( $post, $tableName = 'addresses', $prefix = '') {

        global $TABLE;
        global $ALERT;

        $table = strtoupper($tableName);

        $RETURN = (object) [];

        foreach ($post as $key => $value) { 
            if (str_contains($key, $prefix) && !empty($value)) { 
                $POST[str_replace($prefix, '', $key)] = $value; 
            } 
        }

        $VALUES = formToArray($tableName, $POST, $TABLE->$table, $POST['id'] ?? null);

        foreach ($VALUES as $key => $value) { $RETURN->values[$prefix.$key] = $value; }

        if (empty($ALERT) && count($VALUES) > 1) {
            
            if (isset($POST['id']) && !empty($POST['id'])) {

                $RETURN->id = $POST['id'];
                sqlModify($tableName, $VALUES, 'id', $RETURN->id);

            } else {
        
                $SQL = sqlInsert($tableName, $VALUES);
                $RETURN->id = $SQL->insert_id;
        
            }
        }

        return $RETURN;

    }

    function getAddress($id, $table = 'addresses') {

        $RETURN = info($table, 'id', $id);

        $addressMore = empty($RETURN->more) ? "" : "<br>$RETURN->more";
        $addressMorePDF = empty($RETURN->more) ? "" : "\n$RETURN->more";

        $RETURN->prettyAddress = "--";
        $RETURN->address = "$RETURN->street $RETURN->number, $RETURN->cap $RETURN->city ($RETURN->province)";

        $RETURN->phone_prefix = isset($RETURN->phone_prefix) ? $RETURN->phone_prefix : "";
        $RETURN->prettyPhone = empty($RETURN->phone) ? "" : $RETURN->phone_prefix.' '.prettyPhone($RETURN->phone);

        if (isset($RETURN->type)) {
            
            if ($RETURN->type == 'private') {
                
                $RETURN->prettyAddress = "
                <b>$RETURN->name $RETURN->surname</b><br>
                $RETURN->cf<br>
                $RETURN->street $RETURN->number, $RETURN->cap <br>
                $RETURN->city ($RETURN->province$addressMore)";

                $RETURN->prettyPDF = "$RETURN->name $RETURN->surname\n$RETURN->cf\n$RETURN->street $RETURN->number, $RETURN->cap\n$RETURN->city ($RETURN->province)$addressMorePDF";

            } elseif ($RETURN->type == 'business') {

                if ($RETURN->pi == $RETURN->cf || empty($RETURN->cf)) {
                    $fiscal = "P.Iva $RETURN->pi<br>";
                    $fiscalPDF = "P.Iva $RETURN->pi\n";
                } else if ($RETURN->pi != $RETURN->cf || (!empty($RETURN->pi) && !empty($RETURN->cf))) {
                    $fiscal = "P.Iva $RETURN->pi<br>C.F. $RETURN->cf<br>";
                    $fiscalPDF = "P.Iva $RETURN->pi\nC.F. $RETURN->cf\n";
                } else {
                    $fiscal = "";
                    $fiscalPDF = "";
                }

                $RETURN->prettyAddress = "
                <b>$RETURN->business_name</b><br>
                $fiscal
                $RETURN->street $RETURN->number, $RETURN->cap <br>
                $RETURN->city ($RETURN->province)$addressMore";

                $RETURN->prettyPDF = "$RETURN->business_name\n$fiscalPDF$RETURN->street $RETURN->number, $RETURN->cap\n$RETURN->city ($RETURN->province)$addressMorePDF";

            }
            
        } else {

            $address = prettyAddress($RETURN->street, $RETURN->number, $RETURN->cap, $RETURN->city, $RETURN->province, $RETURN->country, $RETURN->more, $RETURN->name, $RETURN->surname, $RETURN->prettyPhone);

            $RETURN->prettyAddress = $address->pretty;
            $RETURN->prettyPDF = $address->prettyPDF;
            
        }

        return $RETURN;

    }