<?php

    function sortableInput($TITLE, $ID, $INPUT, $INPUT_VALUES = null) {

        # Bottoni default

            $BUTTON_UP = "
            <button type='button' class='btn btn-light btn-sm wi-arrow-up' onclick=\"rowOrder(this.parentElement.parentElement.parentElement, 'up')\" style='font-size: .8em;' data-bs-toggle='tooltip' data-bs-title='Sposta linea su'>
                <i class='bi bi-chevron-up'></i>
            </button>";

            $BUTTON_DOWN = "
            <button type='button' class='btn btn-light btn-sm wi-arrow-down' onclick=\"rowOrder(this.parentElement.parentElement.parentElement, 'down')\" style='font-size: .8em;' data-bs-toggle='tooltip' data-bs-title='Sposta linea giù'>
                <i class='bi bi-chevron-down'></i>
            </button>";

            $BUTTON_DELETE = "
            <button type='button' class='btn btn-danger btn-sm float-end' onclick=\"rowRemoveModal(this.parentElement.parentElement.parentElement)\" data-bs-toggle='tooltip' data-bs-title='Elimina linea'>
                <i class='bi bi-trash3'></i>
            </button>";

        #

        $RETURN = "<div class='row g-3'>";

        # Titolo

            $RETURN .= "<div class='col-12'> <h5>$TITLE</h5> </div>";

        #

        # Apro il contenitore

            $RETURN .= "<div class='col-12 mt-0'> <div id='$ID' class='row g-3 mt-0'>";

        #

        if ($INPUT_VALUES != null && count($INPUT_VALUES) > 0) {
            
            foreach ($INPUT_VALUES as $key => $array) {

                $id = isset($array['id']) ? $array['id'] : '';
                $position = $array['position'];

                $RETURN .= "<div class='col-12 wi-copy-row order-$position'>";
                $RETURN .= "<input type='hidden' name='id[]' value='$id'>";
                $RETURN .= "<input type='hidden' name='position[]' value='$position'>";
                $RETURN .= "<div class='row g-2'>";
                $RETURN .= "<div class='col-1'>$BUTTON_UP$BUTTON_DOWN</div>";

                foreach ($INPUT as $name => $value) {

                    $label = $value['label'];
                    $type = $value['type'];
                    $option = isset($value['option']) ? $value['option'] : [];
                    $version = isset($value['version']) ? $value['version'] : null;
                    $attribute = isset($value['attribute']) ? $value['attribute'] : null;
                    $col = isset($value['col']) ? $value['col'] : 1;
                    $value = $array[$name];

                    $RETURN .= "<div class='col-$col'>";

                    if ($type == 'text') {
                        $RETURN .= text($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'number') {
                        $RETURN .= number($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'price') {
                        $RETURN .= price($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'select') {
                        $RETURN .= select($label, $name.'[]', $option, $version, $attribute, $value);
                    } elseif ($type == 'date') {
                        $RETURN .= dateInput($label, $name.'[]', null, null, $attribute, $value);
                    } elseif ($type == 'date-time') {
                        $RETURN .= textDatetime($label, $name.'[]', $attribute, $value);
                    }

                    $RETURN .= "</div>";

                }

                $RETURN .= "<div class='col-1'>$BUTTON_DELETE</div>";
                $RETURN .= "</div>";
                $RETURN .= "</div>";

            }

        } else {

            # Prima linea

                $RETURN .= "<div class='col-12 wi-copy-row order-1'>";
                $RETURN .= "<input type='hidden' name='position[]' value='1'>";
                $RETURN .= "<div class='row g-2'>";
                $RETURN .= "<div class='col-1'>$BUTTON_UP$BUTTON_DOWN</div>";

                foreach ($INPUT as $name => $value) {

                    $label = $value['label'];
                    $type = $value['type'];
                    $option = isset($value['option']) ? $value['option'] : [];
                    $version = isset($value['version']) ? $value['version'] : null;
                    $attribute = isset($value['attribute']) ? $value['attribute'] : null;
                    $col = isset($value['col']) ? $value['col'] : 1;
                    $value = isset($value['value']) ? $value['value'] : null;

                    $RETURN .= "<div class='col-$col'>";

                    if ($type == 'text') {
                        $RETURN .= text($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'number') {
                        $RETURN .= number($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'price') {
                        $RETURN .= price($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'select') {
                        $RETURN .= select($label, $name.'[]', $option, $version, $attribute, $value);
                    } elseif ($type == 'date') {
                        $RETURN .= dateInput($label, $name.'[]', null, null, $attribute, $value);
                    } elseif ($type == 'date-time') {
                        $RETURN .= textDatetime($label, $name.'[]', $attribute, $value);
                    }

                    $RETURN .= "</div>";

                }

                $RETURN .= "<div class='col-1'>$BUTTON_DELETE</div>";
                $RETURN .= "</div>";
                $RETURN .= "</div>";

            #

        }

        # Linea da copiare

            $RETURN .= "<div id='copy-line' class='col-12 visually-hidden'>";
            $RETURN .= "<input type='hidden' name='position[]' value=''>";
            $RETURN .= "<div class='row g-2'>";
            $RETURN .= "<div class='col-1'>$BUTTON_UP$BUTTON_DOWN</div>";

            foreach ($INPUT as $name => $value) {

                $label = $value['label'];
                $type = $value['type'];
                $option = isset($value['option']) ? $value['option'] : [];
                $version = isset($value['version']) ? $value['version'] : null;
                $attribute = isset($value['attribute']) ? $value['attribute'] : null;
                $col = isset($value['col']) ? $value['col'] : 1;
                $value = isset($value['value']) ? $value['value'] : null;

                $RETURN .= "<div class='col-$col'>";

                if ($type == 'text') {
                    $RETURN .= text($label, $name.'[]', 'data-wi-attribute="'.$attribute.'"', $value);
                } elseif ($type == 'number') {
                    $RETURN .= number($label, $name.'[]', 'data-wi-attribute="'.$attribute.'"', $value);
                } elseif ($type == 'price') {
                    $RETURN .= price($label, $name.'[]', 'data-wi-attribute="'.$attribute.'"', $value);
                } elseif ($type == 'select') {
                    $RETURN .= select($label, $name.'[]', $option, $version, 'data-wi-attribute="'.$attribute.'"', $value);
                } elseif ($type == 'date') {
                    $RETURN .= dateInput($label, $name.'[]', null, null, 'data-wi-attribute="'.$attribute.'"', $value);
                } elseif ($type == 'date-time') {
                    $RETURN .= textDatetime($label, $name.'[]', 'data-wi-attribute="'.$attribute.'"', $value);
                }

                $RETURN .= "</div>";

            }

            $RETURN .= "<div class='col-1'>$BUTTON_DELETE</div>";
            $RETURN .= "</div>";
            $RETURN .= "</div>";

        #

        # Chiudo il contenitore

            $RETURN .= "</div> </div>";

        #

        # Bottone per aggiungere

            $RETURN .= "<div class='col-12'>";
            $RETURN .= "<div class='btn btn-secondary float-end' onclick=\"copyRow(document.querySelector('#$ID'), document.querySelector('#copy-line'));\" role='button' data-bs-toggle='tooltip' data-bs-title='Aggiungi linea'><i class='bi bi-plus-lg'></i></div>";
            $RETURN .= "</div>";

        #

        $RETURN .= "</div>";

        # Sistema le frecce 

            $RETURN .= "<script> rowSetArrow(document.querySelector('#$ID')); </script>";

        #

        return $RETURN;

    }

?>