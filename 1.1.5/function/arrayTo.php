<?php

    function arrayToCsv($array, $filename) {

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=$filename.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        $output = fopen('php://output', 'w' );

        foreach ($array as $fields) {
            fputcsv($output, $fields);
        }
        
        fclose($output);
        
    }

    function arrayToXls($array, $filename) {

        header("Content-Disposition: attachment; filename=\"$filename.xls\"");
        header("Content-Type: application/vnd.ms-excel;");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen("php://output", 'w');
        foreach ($array as $data) {
            fputcsv($output, $data,"\t");
        }

        fclose($output);
    
    }

?>