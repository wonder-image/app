<?php

    function arrayToCsv($ARRAY, $FILENAME = null) {

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=$FILENAME.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        $output = fopen('php://output', 'w' );

        foreach ($ARRAY as $row) { fputcsv($output, $row); }
        
        fclose($output);
        
    }

    function arrayToXls($ARRAY, $FILENAME = null) {

        header("Content-Type: application/vnd.ms-excel;");
        header("Content-Disposition: attachment; filename=\"$FILENAME.xls\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        $output = fopen("php://output", 'w');

        foreach ($ARRAY as $row) { fputcsv($output, $row,"\t"); }

        fclose($output);
    
    }

    function arrayToXml($ARRAY, $FILENAME = null, $VERSION = "1.0", $ENCODING = "UTF-8") {

        $XML = new DOMDocument($VERSION, $ENCODING);

        $XML->preserveWhiteSpace = true;
        $XML->formatOutput = true;

        $XML = createXML($XML, $ARRAY);

        $XML = $XML->saveXML();

        if ($FILENAME == null) {

            return $XML;

        } else {

            header("Content-Type: text/xml");
            header("Content-Disposition: attachment; filename=\"$FILENAME.xml\"");
            header("Pragma: no-cache");
            header("Expires: 0");

            echo $XML;

        }

    }

    function createXml($XML, $CHILD, $PARENT = null) {

        if ($PARENT == null) {
            $PARENT = $XML;
        }
        
        foreach ($CHILD as $NAME => $VALUE) {
            
            if (is_array($VALUE)) {

                $elementValue = isset($VALUE['value']) ? $VALUE['value'] : "";
                $elementAttributes = isset($VALUE['attributes']) ? $VALUE['attributes'] : "";
                $elementChild = isset($VALUE['child']) ? $VALUE['child'] : "";

                $element = $XML->createElement($NAME, $elementValue);
    
                if (is_array($elementAttributes)) {
                    foreach ($elementAttributes as $attribute => $value) { $element->setAttribute($attribute, $value); }
                }

                $PARENT = $PARENT->appendChild($element);

                if (is_array($elementChild)) {
                    foreach ($elementChild as $childName => $childValue) { 

                        $child = [];
                        $child[$childName] = $childValue;

                        if (is_array($childValue)) {
                            $XML = createXml($XML, $child, $PARENT); 
                        } else {
                            $element = $XML->createElement($childName, $childValue);
                            $PARENT->appendChild($element);
                        }

                    }
                }

                if (empty($elementValue) && empty($elementAttributes) && empty($elementChild)) {
                    foreach ($VALUE as $childName => $childValue) { 

                        $child = [];
                        $child[$childName] = $childValue;

                        if (is_array($childValue)) {
                            $XML = createXml($XML, $child, $PARENT); 
                        } else {
                            $element = $XML->createElement($childName, $childValue);
                            $PARENT->appendChild($element);
                        }

                    }
                } 

            } else {

                $element = $XML->createElement($NAME, $VALUE);
                $PARENT->appendChild($element);

            }
            
        }

        return $XML;

    }