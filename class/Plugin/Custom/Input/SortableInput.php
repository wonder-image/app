<?php

    namespace Wonder\Plugin\Custom\Input;

    class SortableInput {

        public $Id;
        public $Title;
        public $Button;
        public $Columns = [];
        public $RowN = 1;
        public $Values = [ [ ] ];

        function __construct( $Id, $Values = null ) {

            $this->Id = $Id;

            $this->Button = [
                'up' => '<button type="button" class="btn btn-light btn-sm wi-arrow-up" onclick="rowOrder(this.parentElement.parentElement.parentElement, \'up\')" style="font-size: .8em;" data-bs-toggle="tooltip" data-bs-title="Sposta linea su"> <i class="bi bi-chevron-up"></i> </button>',
                'down' => '<button type="button" class="btn btn-light btn-sm wi-arrow-down" onclick="rowOrder(this.parentElement.parentElement.parentElement, \'down\')" style="font-size: .8em;" data-bs-toggle="tooltip" data-bs-title="Sposta linea giù"> <i class="bi bi-chevron-down"></i> </button>',
                'delete' => '<button type="button" class="btn btn-danger btn-sm float-end" onclick="rowRemoveModal(this.parentElement.parentElement.parentElement)" data-bs-toggle="tooltip" data-bs-title="Elimina linea"> <i class="bi bi-trash3"></i> </button>',
                'add_row' => '<button type="button" class="btn btn-secondary float-end" onclick="copyRow(document.getElementById(\''.$this->Id.'\'), document.querySelector(\'#copy-line-'.$this->Id.'\'));" role="button" data-bs-toggle="tooltip" data-bs-title="Aggiungi linea"> <i class="bi bi-plus-lg"></i> </button>'
            ];

            if ($Values != null && !empty($Values)) {
                $this->Values = $Values;
            }

        }

        function Title( $title) { $this->Title = $title; }

        function Column( $name, $label = '', $type = 'hidden', $value = null, int $col = 3, $attribute = '', array $option = [] ) {

            $Column = [
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'value' => $value,
                'col' => $col,
                'attribute' => $attribute,
                'option' => $option
            ];

            array_push($this->Columns, $Column);

        }
        
        private function GenerateRow( $Value, $CopyRow = false ) {

            if ($CopyRow) {

                $RETURN = '<div id="copy-line-'.$this->Id.'" class="col-12 visually-hidden">';

            } else {

                $RETURN = '<div class="col-12 wi-copy-row order-'.$this->RowN.'">';
                $this->RowN++;

            }

            $RETURN .= '<div class="row g-2">';
            $RETURN .= '<div class="col-1">'.$this->Button['up'].$this->Button['down'].'</div>';

            foreach ($this->Columns as $key => $Column) {
                
                $label = $Column['label'];
                $name = $Column['name'];
                $type = $Column['type'];
                $value = isset($Value[$name]) ? $Value[$name] : $Column['value'];
                $col = $Column['col'];
                $attribute = $Column['attribute'];
                $option = $Column['option'];

                if ($CopyRow) { $attribute = 'data-wi-attribute="'.$attribute.'"'; }

                if ($type == 'hidden') {

                    $RETURN .= '<input type="hidden" name="'.$name.'[]" value="'.$value.'" '.$attribute.'>';

                } else {

                    $RETURN .= "<div class='col-$col'>";

                    if ($type == 'text') {
                        $RETURN .= text($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'number') {
                        $RETURN .= number($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'price') {
                        $RETURN .= price($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'select') {
                        $RETURN .= select($label, $name.'[]', $option, null, $attribute, $value);
                    } elseif ($type == 'date') {
                        $RETURN .= dateInput($label, $name.'[]', null, null, $attribute, $value);
                    } elseif ($type == 'date-time') {
                        $RETURN .= textDatetime($label, $name.'[]', $attribute, $value);
                    }

                    $RETURN .= "</div>";

                }

            }

            $RETURN .= '<div class="col-1">'.$this->Button['delete'].'</div>';
            $RETURN .= '</div>';
            $RETURN .= '</div>';

            return $RETURN;

        }

        function Generate() {

            $RETURN = "<div class='row g-3'>";

            # Titolo
            if (!empty($this->Title)) { $RETURN .= '<div class="col-12 mt-0"> <h5>'.$this->Title.'</h5> </div>'; }

            # Apro il contenitore
            $RETURN .= '<div class="col-12 mt-0">';
            $RETURN .= '<div id="'.$this->Id.'" class="row g-3 mt-0">';

            # Input
            foreach ($this->Values as $key => $Value) {
                
                $RETURN .= $this->GenerateRow($Value);

            }

            # Linea da copiare
            $RETURN .= $this->GenerateRow([], true);

            # Chiudo il contenitore
            $RETURN .= '</div>';
            $RETURN .= '</div>';

            # Bottone per aggiungere
            $RETURN .= '<div class="col-12">'.$this->Button['add_row'].'</div>';

            $RETURN .= "</div>";

            # Sistema le frecce 
            $RETURN .= '<script> rowSetArrow(document.getElementById(\''.$this->Id.'\')); </script>';

            return $RETURN;

        }
        
    }
