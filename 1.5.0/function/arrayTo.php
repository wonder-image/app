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

    function arrayToXls($array, $fileName = 'export') {

        $array = is_array($array) ? $array : [$array];

        $fileName = empty($fileName) ? 'export' : trim((string) $fileName);

        $sheetName = create_link($fileName);

        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        foreach (array_values($array) as $rowIndex => $row) {

            if (!is_array($row)) { $row = [ $row ]; }

            foreach (array_values($row) as $columnIndex => $cell) {

                $coordinate = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 1);

                $dataType = PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
                $value = '';

                if ($cell instanceof DateTimeInterface) {
                    $value = PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($cell);
                    $dataType = PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                } elseif (is_bool($cell)) {
                    $dataType = PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_BOOL;
                    $value = $cell;
                } elseif (is_int($cell) || is_float($cell)) {
                    $dataType = PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                    $value = $cell;
                } elseif (is_string($cell)) {
                    $trimmed = trim($cell);
                    if ($trimmed !== '' && is_numeric($trimmed) && !preg_match('/^0\d+$/', $trimmed)) {
                        $dataType = PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
                        $value = $trimmed;
                    } else {
                        $value = $cell;
                    }
                } elseif ($cell === null) {
                    $value = '';
                } else {
                    $value = (string) $cell;
                }

                $sheet->setCellValueExplicit($coordinate, $value, $dataType);

            }
        }

        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"{$fileName}.xlsx\"");
        header("Cache-Control: max-age=0");
        header("Pragma: public");
        header("Expires: 0");

        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save('php://output');

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