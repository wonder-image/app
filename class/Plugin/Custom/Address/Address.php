<?php

    namespace Wonder\Plugin\Custom\Address;
    
    use Wonder\Sql\Query;
    use Wonder\Plugin\Custom\Prettify;
    
    class Address {

        public $tableName, $table, $prefix;

        public array $DEFAULT;

        public $platform = 'frontend';
        
        static array | string $condition = [ 'deleted' => 'false' ];

        public function __construct( $table, $prefix = '' ) {

            global $TABLE;

            $this->tableName = $table;

            $table = strtoupper($table);
            $this->table = $TABLE->$table;

            $this->prefix = $prefix;

            $this->DEFAULT['type'] = 'private';
            $this->DEFAULT['country'] = $_SESSION['system_cache']['country'];
            $this->DEFAULT['phone_prefix'] = countryPhonePrefix($this->DEFAULT['country']);

        }

        public function create( array $post ): object {

            global $ALERT;

            $RETURN = (object) [];
            $POST = [];
    
            foreach ($post as $key => $value) { 
                if (str_contains($key, $this->prefix) && (!empty($value) || !empty($POST['id']))) { 

                    $realKey = str_replace($this->prefix, '', $key);
                    $POST[$realKey] = $value; 

                } 
            }
    
            $VALUES = formToArray($this->tableName, $POST, $this->table, isset($POST['id']) ? $POST : null);
    
            foreach ($VALUES as $key => $value) { $RETURN->values[$this->prefix.$key] = $value; }
    
            if (empty($ALERT) && (count($VALUES) > 4 && !isset($VALUES['type']) || (count($VALUES) > 5 && isset($VALUES['type'])))) {
                
                if (isset($POST['id']) && !empty($POST['id'])) {
    
                    $RETURN->id = $POST['id'];
                    sqlModify($this->tableName, $VALUES, 'id', $RETURN->id);
    
                } else {
            
                    $SQL = sqlInsert($this->tableName, $VALUES);
                    $RETURN->id = $SQL->insert_id;
            
                }
            }
    
            return $RETURN;


        }

        public function getById( $value ) {

            $RETURN = info($this->tableName, 'id', $value);

            $addressMore = empty($RETURN->more) ? "" : "<br>$RETURN->more";
            $addressMorePDF = empty($RETURN->more) ? "" : "\n$RETURN->more";
    
            $RETURN->prettyAddress = "--";
            $RETURN->address = "$RETURN->street $RETURN->number, $RETURN->cap $RETURN->city ($RETURN->province)";
    
            $RETURN->phone_prefix = $RETURN->phone_prefix ?? "";
            $RETURN->prettyPhone = empty($RETURN->phone) ? "" : Prettify::Phone("{$RETURN->phone_prefix}{$RETURN->phone}");
    
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

        private function mergeConditions($condition1, $condition2) {

            if (is_array($condition1) && is_array($condition2)) {
                
                return array_merge($condition1, $condition2);

            } else if (is_array($condition1) && !is_array($condition2)) {

                $query = new Query();
                $condition1 = $query->Conditions($condition1, false);
                return "$condition1 AND $condition2";

            } else if (!is_array($condition1) && is_array($condition2)) {
                
                $query = new Query();
                $condition2 = $query->Conditions($condition2, false);
                return "$condition1 AND $condition2";

            } else if (!is_array($condition1) && !is_array($condition2)) {

                return "$condition1 AND $condition2";

            }

        }

        public function get( array | string $condition, $limit = null) {

            return sqlSelect(
                $this->tableName, 
                $this->mergeConditions($condition, self::$condition),
                $limit
            );

        }

        public function getValues( array | string $condition ) {

            $SQL = sqlSelect( 
                $this->tableName, 
                $this->mergeConditions($condition, self::$condition), 
                1
            );

            $return = [];

            if ($SQL->exists) { 

                foreach ($this->getById( $SQL->row['id'] ) as $key => $value) { 
                    $return["{$this->prefix}$key"] = $value; 
                } 
                
            } else {
                
                $return["{$this->prefix}prettyAddress"] = '--';
                $return["{$this->prefix}prettyPDF"] = '--';
                
            }

            return $return;

        }

        public function delete( $value, $column = 'id' ) {

            global $ALERT;

            if (empty($ALERT)) {

                sqlModify($this->tableName, [ "deleted" => "true" ], $column, $value);

            }

        }

        public function modal($id, $content) {

            return "
            <div id='$id' class='modal fade' id='modal' tabindex='-1' aria-labelledby='modalLabel' aria-hidden='true'>
                <div class='modal-dialog'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h1 class='modal-title fs-5' id='modalLabel'></h1>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body row g-3'>$content</div>
                    </div>
                </div>
            </div>";

        }

        public function modalDelete($form = false) {
            
            $content = $form ? "<form action=\"\" method=\"post\" enctype=\"multipart/form-data\" onsubmit=\"loadingSpinner()\">" : "";

            $content .= "<input type=\"hidden\" name=\"{$this->prefix}delete-id\">";
            $content .= "<div class=\"col-12 value\"></div>";
            $content .= '<div class="col-12">'.submit('Elimina', "{$this->prefix}delete", 'btn-danger').'</div>';

            $content .= $form ? "</form>" : "";

            return $this->modal("{$this->prefix}modal-delete", $content);

        }

        public function modalForm($type = null,$form = false) {
            
            $content = $form ? "<form action=\"\" method=\"post\" enctype=\"multipart/form-data\" onsubmit=\"loadingSpinner()\">" : "";

            $content .= $this->input($type, true, false);

            $content .= '<div class="col-12">'.submit('Salva', "{$this->prefix}save").'</div>';

            $content .= $form ? "</form>" : "";
            
            return $this->modal("{$this->prefix}modal-form", $content);

        }

        public function input($billing = null, $required = false, $init = true) {

            global $VALUES;
            
            $rowId = $VALUES["{$this->prefix}id"] ?? '';

            $type = $VALUES["{$this->prefix}type"] ?? $this->DEFAULT['type'];
            $country = $VALUES["{$this->prefix}country"] ?? $this->DEFAULT['country'];
            $phonePrefix = $VALUES["{$this->prefix}phone_prefix"] ?? $this->DEFAULT['phone_prefix'];

            $ID = "<input type=\"hidden\" name=\"{$this->prefix}id\" value=\"$rowId\">";

            if ($billing == 'all') {
                $TYPE = '<div class="col-12">'.select('Tipologia', "{$this->prefix}type", [ 'private' => 'Privato', 'business' => 'Società' ], $type, "onchange=\"{$this->prefix}FORM.initForm()\" disabled").'</div>';
            } else if ($billing == 'business') {
                $TYPE = "<input type=\"hidden\" name=\"{$this->prefix}type\" value=\"business disabled\">";
            } else if ($billing == 'private') {
                $TYPE = "<input type=\"hidden\" name=\"{$this->prefix}type\" value=\"private disabled\">";
            }

            $FULL_NAME = '<div class="col-6">'.text('Nome', "{$this->prefix}name", "disabled").'</div>';
            $FULL_NAME .= '<div class="col-6">'.text('Cognome', "{$this->prefix}surname", "disabled").'</div>';

            $BUSINESS_NAME = '<div class="col-12">'.text('Ragione sociale', "{$this->prefix}business_name", "disabled").'</div>';
            
            $CF = '<div class="col-12">'.text('C.Fiscale', "{$this->prefix}cf", "disabled").'</div>';

            $PI = '<div class="col-12">'.text('P.Iva', "{$this->prefix}pi", "disabled").'</div>';
            $SDI = '<div class="col-4">'.text('SDI', "{$this->prefix}sdi", "disabled").'</div>';
            $PEC = '<div class="col-8">'.email('PEC', "{$this->prefix}pec", "disabled").'</div>';

            $ADDRESS = '<div class="col-6">'.inputCountry('Paese', "{$this->prefix}country", $country, "{$this->prefix}province", "disabled").'</div>';
            $ADDRESS .= '<div class="col-6">'.inputStates('Provincia', "{$this->prefix}province", $country, $VALUES["{$this->prefix}province"] ?? null, "disabled").'</div>';
            $ADDRESS .= '<div class="col-4">'.text('Cap', "{$this->prefix}cap", "disabled").'</div>';
            $ADDRESS .= '<div class="col-8">'.text('Città', "{$this->prefix}city", "disabled").'</div>';
            $ADDRESS .= '<div class="col-8">'.text('Via/Viale/Piazza', "{$this->prefix}street", "disabled").'</div>';
            $ADDRESS .= '<div class="col-4">'.text('Numero', "{$this->prefix}number", "disabled").'</div>';

            $MORE = '<div class="col-12">'.text('Altre indicazioni', "{$this->prefix}more", "disabled").'</div>';

            $PHONE = '<div class="col-4">'.inputPhonePrefix('Prefisso', "{$this->prefix}phone_prefix", $phonePrefix, "disabled").'</div>';
            $PHONE .= '<div class="col-8">'.phone('Cellulare', "{$this->prefix}phone", "disabled").'</div>';

            if (is_null($billing)) {
                $FORM = "$ID$FULL_NAME$PHONE$ADDRESS$MORE";
                $JS_CLASS = "Address";
            } else {
                $FORM = "$ID$TYPE$FULL_NAME$BUSINESS_NAME$PI$CF$SDI$PEC$ADDRESS$PHONE";
                $JS_CLASS = "Billing";
            }

            $SCRIPT = "<script>";
            $SCRIPT .= "var {$this->prefix}FORM;";
            $SCRIPT .= "window.addEventListener('loaded', () => {";
            $SCRIPT .= "{$this->prefix}FORM = new $JS_CLASS(document.querySelector('input[name=\"{$this->prefix}id\"]').form, '{$this->prefix}', $required);";
            $SCRIPT .= $init ? "{$this->prefix}FORM.initForm();" : "";
            $SCRIPT .= "});";
            $SCRIPT .= "</script>";

            return $FORM.$SCRIPT;
        
        }

        public function card($id, $modify = false, $delete = false) {

            $ADDRESS = $this->getById($id);

            if (isset($ADDRESS->type)) {
                $datasetArray = [ 'id', 'type', 'business_name', 'name', 'surname', 'cf', 'pi', 'sdi', 'pec', 'country', 'province', 'cap', 'city', 'street', 'number', 'phone_prefix', 'phone' ];
            } else {
                $datasetArray = [ 'id', 'name', 'surname', 'country', 'province', 'cap', 'city', 'street', 'number', 'phone_prefix', 'phone', 'more' ];
            }

            $DATASET = "";

            foreach ($datasetArray as $key) {

                $datasetKey = str_replace('_', '-', $key);
                $DATASET .= "data-{$datasetKey}=\"".str_replace( '"', "&quot;", $ADDRESS->$key)."\" ";

            }

            $BTN_MODIFY = $modify ? "<button type=\"button\" class=\"btn btn-sm btn-warning float-end\" data-bs-toggle=\"tooltip\" data-bs-title=\"Modifica\" onclick=\"{$this->prefix}FORM.setForm(this.dataset);{$this->prefix}FORM.modalForm();\" $DATASET ><i class=\"bi bi-pencil-square\"></i></button>" : "";
            $BTN_DELETE = $delete ? "<button type=\"button\" class=\"btn btn-sm btn-danger float-end ms-2\" data-bs-toggle=\"tooltip\" data-bs-title=\"Elimina\" onclick=\"{$this->prefix}FORM.modalDelete('$ADDRESS->id', this.dataset.address)\" data-address=\"".str_replace( '"', "&quot;", $ADDRESS->prettyAddress)."\"><i class=\"bi bi-trash3\"></i></button>" : "";

            $footer = (empty($BTN_MODIFY) && empty($BTN_DELETE)) ? "" : "<div class=\"card-footer\">$BTN_DELETE$BTN_MODIFY</div>";

            return "
                <div class=\"col-6\">
                    <div class=\"card\">
                        <div class=\"card-body\">$ADDRESS->prettyAddress</div>
                        $footer
                    </div>
                </div>";

            
        }

        public function buttonAdd($text = true, $icon = false, $class = '') {

            $content = $icon ? "<i class=\"bi bi-plus-lg\"></i>" : "";
            $content .= $text ? " Aggiungi" : "";

            return "<button type=\"button\" class=\"btn btn-sm btn-success float-end $class\" data-bs-toggle=\"tooltip\" data-bs-title=\"Aggiungi\" onclick=\"{$this->prefix}FORM.initForm();{$this->prefix}FORM.modalForm('add');\">$content</button>";

        }

    }
