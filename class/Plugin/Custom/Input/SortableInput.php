<?php

    namespace Wonder\Plugin\Custom\Input;

    class SortableInput {

        public $Id;
        public $Title;
        public $Button;
        public bool $Position = true;
        public bool $Add = true;
        public bool $Delete = true;
        public $Columns = [];
        public $RowN = 1;
        public $Values = [ [ ] ];

        # Callback
        public array $addCallback, $deleteCallback, $upCallback, $downCallback = [];


        function __construct( $Id, $Values = null ) {

            $this->Id = $Id;

            $this->ConfButtons();

            if ($Values != null && !empty($Values)) { $this->Values = $Values; }

        }

        function Title( $title) { $this->Title = $title; }
        function Position( bool $position = true ) { $this->Position = $position; }
        function Add( bool $add = true ) { $this->Add = $add; }
        function Delete( bool $delete = true ) { $this->Delete = $delete; }

        function OnAdd( $callback ) { $this->addCallback[] = $callback; }
        function OnDelete( $callback ) { $this->deleteCallback[] = $callback; }
        function OnUp( $callback ) { $this->upCallback[] = $callback; }
        function OnDown( $callback ) { $this->downCallback[] = $callback; }


        function ConfButtons() {

            $this->Button = [
                'up' => '<button type="button" class="btn btn-light btn-sm wi-arrow-up" onclick="rowOrder(this.parentElement.parentElement.parentElement, \'up\', SI_up_'.$this->Id.')" style="font-size: .8em;" data-bs-toggle="tooltip" data-bs-title="Sposta linea su"> <i class="bi bi-chevron-up"></i> </button>',
                'down' => '<button type="button" class="btn btn-light btn-sm wi-arrow-down" onclick="rowOrder(this.parentElement.parentElement.parentElement, \'down\', SI_down_'.$this->Id.')" style="font-size: .8em;" data-bs-toggle="tooltip" data-bs-title="Sposta linea giù"> <i class="bi bi-chevron-down"></i> </button>',
                'delete' => '<button type="button" class="btn btn-danger btn-sm float-end" onclick="rowRemoveModal(this.parentElement.parentElement.parentElement, SI_delete_'.$this->Id.')" data-bs-toggle="tooltip" data-bs-title="Elimina linea"> <i class="bi bi-trash3"></i> </button>',
                'add_row' => '<button id="add-row-'.$this->Id.'" type="button" class="btn btn-secondary float-end" onclick="copyRow(document.getElementById(\''.$this->Id.'\'), document.querySelector(\'#copy-line-'.$this->Id.'\'), SI_add_'.$this->Id.');" role="button" data-bs-toggle="tooltip" data-bs-title="Aggiungi linea"> <i class="bi bi-plus-lg"></i> </button>'
            ];

        }

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

            $delete = $Value['delete'] ?? $this->Delete;

            if ($CopyRow) {

                $RETURN = '<div id="copy-line-'.$this->Id.'" class="col-12 visually-hidden">';

            } else {

                $RETURN = '<div class="col-12 wi-copy-row order-'.$this->RowN.'">';
                $this->RowN++;

            }

            $RETURN .= '<div class="row g-2">';

            if ($this->Position) {
                $RETURN .= '<div class="col-1">'.$this->Button['up'].$this->Button['down'].'</div>';
            }

            foreach ($this->Columns as $key => $Column) {
                
                $label = $Column['label'];
                $name = $Column['name'];
                $type = $Column['type'];
                $value = $Value[$name] ?? $Column['value'];
                $col = $Column['col'];
                $attribute = $Column['attribute'];
                $option = $Column['option'];

                # Se sono stati passati dei valori diversi nelle linee di default

                    $config = (is_array($value) && isset($value['value'])) ? $value : [];

                    $value = $config['value'] ?? $value;
                    $addAttribute = $config['attribute'] ?? '';

                    $attribute .= ' '.$addAttribute;

                #

                if ($CopyRow && !in_array($type, [ 'select', 'select-search' ])) { $attribute = 'data-wi-attribute="'.str_replace('"', "'",$attribute).'"'; }

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
                    } elseif ($type == 'percentige') {
                        $RETURN .= percentige($label, $name.'[]', $attribute, $value);
                    } elseif ($type == 'select') {
                        $RETURN .= select($label, $name.'[]', $option, null, $attribute, $value);
                    } elseif ($type == 'select-search') {
                        $RETURN .= selectSearch($label, $name.'[]', $option, false, null, $attribute, $value);
                    } elseif ($type == 'date') {
                        $RETURN .= dateInput($label, $name.'[]', null, null, $attribute, $value);
                    } elseif ($type == 'date-time') {
                        $RETURN .= textDatetime($label, $name.'[]', $attribute, $value);
                    }

                    $RETURN .= "</div>";

                }

            }

            if ($delete) {
                $RETURN .= '<div class="col-1">'.$this->Button['delete'].'</div>';
            }

            $RETURN .= '</div>';
            $RETURN .= '</div>';

            return $RETURN;

        }

        function Generate() {

            $RETURN = "<div class='row g-3'>";

            # Titolo
            if (!empty($this->Title)) { $RETURN .= '<div class="col-12 mt-0"> <h6>'.$this->Title.'</h6> </div>'; }

            # Apro il contenitore
            $RETURN .= '<div class="col-12 mt-0">';
            $RETURN .= '<div id="'.$this->Id.'" class="row g-3 mt-0">';

            # Input
            foreach ($this->Values as $key => $Value) { $RETURN .= $this->GenerateRow($Value); }

            # Linea da copiare
            $RETURN .= $this->GenerateRow([], true);

            # Chiudo il contenitore
            $RETURN .= '</div>';
            $RETURN .= '</div>';

            # Bottone per aggiungere
            if ($this->Add) {
                $RETURN .= '<div class="col-12">'.$this->Button['add_row'].'</div>';
            }

            $RETURN .= "</div>";

            # Script
            $RETURN .= '<script>';

                # Sistema le frecce 
                $RETURN .= "rowSetArrow(document.getElementById('{$this->Id}'));\n";

                # Funzioni di callback
                    $RETURN .= "function SI_add_{$this->Id}() {";
                    if (!empty($this->addCallback)) { foreach ($this->addCallback as $fName) { $RETURN .= " $fName(); "; } }
                    $RETURN .= "}\n";

                    $RETURN .= "function SI_delete_{$this->Id}() {";
                    if (!empty($this->deleteCallback)) { foreach ($this->deleteCallback as $fName) { $RETURN .= " $fName(); "; } }
                    $RETURN .= "}\n";

                    $RETURN .= "function SI_up_{$this->Id}() {";
                    if (!empty($this->upCallback)) { foreach ($this->upCallback as $fName) { $RETURN .= " $fName(); "; } }
                    $RETURN .= "}\n";

                    $RETURN .= "function SI_down_{$this->Id}() {";
                    if (!empty($this->downCallback)) { foreach ($this->downCallback as $fName) { $RETURN .= " $fName(); "; } }
                    $RETURN .= "}\n";
                #

            # End Script
            $RETURN .= '</script>';

            return $RETURN;

        }
        
    }
