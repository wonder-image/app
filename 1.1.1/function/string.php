<?php

    function create_link($str, $table = null, $column = null, $id = null){

        $str = str_replace("à", "a", $str); 
        $str = str_replace("è", "e", $str); 
        $str = str_replace("à", "a", $str); 
        $str = str_replace("ì", "i", $str); 
        $str = str_replace("ù", "u", $str);
        $str = preg_replace('/[^A-Za-z0-9\-]/', ' ', $str);
        $str = trim($str);
        $str = str_replace('        ', ' ', $str); // 8 spazi
        $str = str_replace('       ', ' ', $str); // 7 spazi
        $str = str_replace('      ', ' ', $str); // 6 spazi
        $str = str_replace('     ', ' ', $str); // 5 spazi
        $str = str_replace('    ', ' ', $str); // 4 spazi
        $str = str_replace('   ', ' ', $str); // 3 spazi
        $str = str_replace('  ', ' ', $str); // 2 spazi
        $str = str_replace(' ', '-', $str); // 1 spazio

        if ($table != null && $column != null) {

            $found = false;
            $link = $str;

            if ($id != null) {
                $QUERY = "`id` != '$id' AND ";
            }else{
                $QUERY = "";
            }

            for ($i=1; $i > 0 && !$found; $i++) {

                if (sqlColumnExists($table, 'deleted')) {

                    $QUERY .= "`deleted` = 'false' AND ";

                } 

                $QUERY .= "`$column` = '$link'";

                $SQL = sqlSelect($table, $QUERY);

                if ($SQL->Nrow == 1) {
                    $link = $str.'-'.$i;
                }else{
                    $found = true;
                }
                
            }

            $str = $link;

        }

        return $str;

    }

    function unique($str, $table, $column, $id = null) {

        // global $ALERT;

        $unique = true;

        if ($id != null) {
            $QUERY = "`id` != '$id' AND ";
        }else{
            $QUERY = "";
        }
    
        if (sqlColumnExists($table, 'deleted')) {

            $QUERY .= "`deleted` = 'false' AND ";

        } 

        $QUERY .= "`$column` = '$str'";

        $SQL = sqlSelect($table, $QUERY);

        if ($SQL->exists) {
            $unique = false;
        }

        // if (!$unique) {
        //     if ($column == 'link') { $ALERT = 971;} 
        //     elseif ($column == 'code') { $ALERT = 972;}
        //     elseif ($column == 'email') { $ALERT = 973;}
        //     elseif ($column == 'username') { $ALERT = 974;}
        //     else { $ALERT = 970;}
        // }

        return $unique;

    }

    function sanitize($str){
        
        $str = str_replace('“','"',$str);
        $str = str_replace('”','"',$str);
        $str = str_replace('’',"'",$str);
        $str = str_replace('…',"...",$str);
        $str = trim($str);
        $str = htmlspecialchars($str, ENT_QUOTES);
        $str = addslashes($str);

        return $str;
        
    }

    function sanitizeFirst($str){

        $str = sanitize($str);
        $str = strtolower($str);
        $str = ucwords($str);
        
        return $str;
        
    }

    function sanitizeEcho($str) {

        if (!empty($str)) {
            $str = htmlspecialchars_decode($str, ENT_QUOTES);
            $str = str_replace('<br />', '', $str);
        }

        return $str;

    }

    function printPDF($str){

        if (!empty($str)) {
            $str = htmlspecialchars_decode($str, ENT_QUOTES);
            $str = iconv('UTF-8', 'windows-1252', $str);
        }
        
        return $str;

    }

    function code($lenght, $type, $first = null){

        if($type == 'all'){
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }

        if($type == 'numbers'){
            $characters = '123456789';
        }

        if($type == 'letters'){
            $characters = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';
        }

        $code = '';
        for ($i = 0; $i < $lenght; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        if(!empty($first)){
            $code = "$first$code";
        }

        return $code;

    }

    function prettyPrint($array) {

        print("<pre>".print_r($array,true)."</pre>");

    }

?>