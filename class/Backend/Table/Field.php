<?php

    namespace Wonder\Backend\Table;

    class Field {

        private $table;
        private $link;
        private $text;
        private $user;


        private $row;
        private $rowId;
        private $column;

        private $redirect;        
        private $redirectBase64;

        public $line = 0;
        private $deleteButton = true;


        /**
         * 
         * Alcune funzioni di questa classe sono esterne come:
         *  - returnButton()
         *  - prettyPhone()
         *  - isEmpty()
         * 
         */


        public function __construct(object $TABLE, object $PATH, object $TEXT, object $USER, object $PAGE) { 

            $this->table = (object) array();
            $this->table->name = $TABLE->table;
            $this->table->connection = $TABLE->connection;
            $this->table->database = $TABLE->database;
            $this->table->folder = $TABLE->folder;
            $this->table->field = $TABLE->field;
            $this->table->page = $TABLE->page;
            $this->table->length = $TABLE->length;

            $this->link = (object) array();
            $this->link->site = $PATH->site;
            $this->link->backend = $PATH->backend;
            $this->link->app = $PATH->app;
            $this->link->api = $PATH->api;
            $this->link->folder = $this->link->backend.'/'.$this->table->folder;
            $this->link->upload = $PATH->upload.'/'.$this->table->folder;

            $this->text = (object) array();
            $this->text->titleS = $TEXT->titleS;
            $this->text->titleP = $TEXT->titleP;
            $this->text->last = $TEXT->last;
            $this->text->all = $TEXT->all;
            $this->text->article = $TEXT->article;
            $this->text->full = $TEXT->full;
            $this->text->empty = $TEXT->empty;
            $this->text->this = $TEXT->this;

            $this->user = (object) array();
            $this->user->area = $USER->area;
            $this->user->authority = $USER->authority;

            $this->redirect = $PAGE->redirect;
            $this->redirectBase64 = $PAGE->redirectBase64;

            $mysqli = $TABLE->connection;

        }

        public function newField($row, $column = null, $format = null) {

            $this->row = $row;
            $this->column = $column;

            if ($this->rowId != $row['id']) { $this->line++; }

            if ($this->line == 1) { $this->line = $this->line + ($this->table->page * $this->table->length); }

            $this->rowId = $row['id'];

            $this->link->view = $this->link->folder.'/view.php?redirect='.$this->redirectBase64.'&id='.$this->rowId;
            $this->link->modify = $this->link->folder.'/?redirect='.$this->redirectBase64.'&modify='.$this->rowId;
            $this->link->download = $this->link->folder.'/download.php?id='.$this->rowId;

            if ($format != null) {
                if ($column == 'action_button') {

                    return $this->actionButton($format);

                } else if ($column == 'position_arrow_up' || $column == 'position_arrow_down') {

                    return $this->positionArrow(
                        ($column == 'position_arrow_up') ? 'up' : 'down',
                        $format
                    );

                } else {

                    return $this->setValue($format);

                }

            }

        }

        private function ajaxRequest($url, $key = []) {

            $defaultKey = [ 'database', 'table', 'id' ];

            foreach ($defaultKey as $q) {

                if ($q == 'database') {
                    $query = "database={$this->table->database}";
                } elseif ($q == 'table') {
                    $query = "table={$this->table->name}";
                } elseif ($q == 'id') {
                    $query = "id={$this->rowId}";
                }

                $link = parse_url($url);
                $url .= isset($link['query']) ? '&' : '?';
                $url .= $query;

            }

            foreach ($key as $q) {

                $link = parse_url($url);
                $url .= isset($link['query']) ? '&' : '?';
                $url .= "$q={$this->row[$q]}";

            }


            return "onclick=\"ajaxRequest(
                '$url',
                reloadDataTable, 
                '#wi-table'
            )\"";

        }

        private function actionButtonItem($text, $action, $bootstrapColor = "", $line = false) {

            return returnButton($text, $action, $bootstrapColor, $line);

        }

        private function deleteRow() {

            $action = "onclick=\"modal(
                'Sei sicuro di voler eliminare {$this->text->this} {$this->text->titleS}?',
                '{$this->link->app}/api/backend/delete.php?database={$this->table->database}&table={$this->table->name}&id={$this->rowId}',
                'ATTENZIONE',
                'Elimina',
                'danger',
                'Chiudi',
                'dark',
                'reloadDataTable', 
                '#wi-table'
            )\"";

            return $this->actionButtonItem("Elimina", $action, 'danger', true);

        }

        private function deleteAuthority() {

            $action = "onclick=\"modal(
                'Sei sicuro di voler eliminare {$this->text->this} {$this->text->titleS}?',
                '{$this->link->app}/api/backend/authority.php?database={$this->table->database}&table={$this->table->name}&id={$this->rowId},
                'ATTENZIONE',
                'Elimina',
                'danger',
                'Chiudi',
                'dark',
                'reloadDataTable', 
                '#wi-table'";
            
            if ($this->user->authority != '') { $action .= "&authority={$this->user->authority}"; }
            if ($this->user->area != '') { $action .= "&area={$this->user->area}"; }

            $action .= "')\"";

            return $this->actionButtonItem("Elimina", $action, 'danger', true);

        }

        private function changeVisible() {

            $action = $this->ajaxRequest("{$this->link->app}/api/backend/visible.php");

            if ($this->row['visible'] == 'true') {

                $text = "Visibile";
                $textButton = "Nascondi";
                $classIcon = "bi bi-eye";
                $bootstrapColor = "success";

            } else {

                $text = "Nascosto";
                $textButton = "Mostra";
                $classIcon = "bi bi-eye-slash";
                $bootstrapColor = "danger";

            }

            return (object) array_merge( 
                (array) returnBadge($text, $classIcon, $bootstrapColor), 
                (array) $this->actionButtonItem($textButton, $action)
            );

        }

        private function changeActive() {

            $action = $this->ajaxRequest("{$this->link->app}/api/backend/active.php");

            if ($this->row['active'] == 'true') {

                $text = "Abilitato";
                $textButton = "Disabilita";
                $classIcon = "bi bi-check-circle";
                $bootstrapColor = "success";

            } else {

                $text = "Disabilitato";
                $textButton = "Abilita";
                $classIcon = "bi bi-x-circle";
                $bootstrapColor = "danger";

            }
            
            return (object) array_merge( 
                (array) returnBadge($text, $classIcon, $bootstrapColor), 
                (array) $this->actionButtonItem($textButton, $action)
            );

        }

        public function actionButton($actionArray) {

            $BUTTONS = "";

            if (!empty($actionArray)) {
                
                foreach ($actionArray as $ACTION => $link) {

                    if ($link && !is_array($link)) {

                        if ($ACTION == 'view') { $BUTTONS .= "<a class='dropdown-item' href='{$this->link->view}' role='button'>Visualizza</a>"; }
                        elseif ($ACTION == 'modify') { $BUTTONS .= "<a class='dropdown-item' href='{$this->link->modify}' role='button'>Modifica</a>"; }
                        elseif ($ACTION == 'download') { $BUTTONS .= "<a class='dropdown-item' href='{$this->link->download}'  target='_blank' rel='noopener noreferrer' role='button'>Scarica</a>"; }
                        elseif ($ACTION == 'visible') { $BUTTONS .= $this->changeVisible()->button; }
                        elseif ($ACTION == 'delete' && $this->deleteButton) { $BUTTONS .= $this->deleteRow()->button; }
                        elseif ($ACTION == 'authority' && $this->deleteButton && isset($this->user->authority) && isset($this->user->area) && !is_array($this->user->area) && !is_array($this->user->authority)) { $BUTTONS .= $this->deleteAuthority()->button; }
                        elseif ($ACTION == 'active') { 
                            if ($this->table->name == 'user') {

                                $userArea = json_decode($this->row['area'], true);
                                $userAuthority = json_decode($this->row['authority'], true);

                                if (count($userArea) <= 1 && count($userAuthority) <= 1) {
                                    $BUTTONS .= $this->changeActive()->button; 
                                }

                            } else {

                                $BUTTONS .= $this->changeActive()->button; 

                            }
                        }

                    } elseif (is_array($link)) {

                        $label = isset($link['label']) ? $link['label'] : '';
                        $href = isset($link['href']) ? $link['href'] : '';
                        $key = isset($link['key']) ? $link['key'] : [];
                        $request = isset($link['request']) ? $link['request'] : '';
                        $action = isset($link['action']) ? $link['action'] : '';
                        $target = isset($link['target']) ? 'target="'.$link['target'].'"' : '';
                        $filter = isset($link['filter']) ? $link['filter'] : [];

                        if ($ACTION == 'delete') {

                            $BUTTON = $this->deleteRow()->button;

                        } else {

                            if (!empty($href)) {

                                preg_match_all('/{(.*?)}/', $href, $listVar);

                                if (count($listVar) > 0) {
                                    foreach ($listVar[1] as $k => $var) {
                                        $href = str_replace('{'.$var.'}', $this->row[$var], $href);
                                    }
                                }

                                foreach ($key as $q) {
    
                                    if ($q == 'database') {
                                        $query = "database={$this->table->database}";
                                    } elseif ($q == 'table') {
                                        $query = "table={$this->table->name}";
                                    } elseif ($q == 'id') {
                                        $query = "id={$this->rowId}";
                                    } elseif ($q == 'redirect') {
                                        $query = "redirect={$this->redirectBase64}";
                                    }
    
                                    $url = parse_url($href);
                                    $href .= isset($url['query']) ? '&' : '?';
                                    $href .= $query;
    
                                }

                                $action = 'href="'.$href.'"';

                            } else if (!empty($request)) {

                                $action = $this->ajaxRequest($this->link->api.'/'.$request, $key);
                                
                            }

                            if (is_array($label)) {
                                $label = $label[$this->row[$ACTION]];
                            }
    
                            $BUTTON = "<a class='dropdown-item' $action role='button' $target>$label</a>";
    
                        }

                        # Controllo se ho i permessi per vedere questo bottone
                        $public = true;

                        if (!empty($filter)) {

                            $public = true;

                            $row = isset($filter['row']) ? $filter['row'] : [];
                            $area = isset($filter['area']) ? $filter['area'] : [];
                            $authority = isset($filter['authority']) ? $filter['authority'] : [];

                            # Controlla che l'area dell'utente sia accettata
                            # Controlla che l'autorità dell'utente sia accettata

                            # Controlla che il valore sia quello specificato
                                if (!empty($row)) {
                                    foreach ($row as $key => $value) {
                                        if ($this->row[$key] != $value) { $public = false; break; }
                                    }
                                }


                        }

                        $BUTTONS .= ($public) ? $BUTTON : "";

                    }

                }

                if (!empty($BUTTONS)) {

                    $BUTTONS = "
                    <div class='btn-group position-static float-end'>
                        <span class='badge text-dark' role='button' data-bs-toggle='dropdown' aria-bs-haspopup='true' aria-bs-expanded='false'>
                            <i class='bi bi-three-dots'></i>
                        </span>
                        <div class='dropdown-menu dropdown-menu-right'>
                            $BUTTONS
                        </div>
                    </div>";

                }

            }

            return $BUTTONS;
            
        }

        public function positionArrow($type, $info) {

            $RETURN = "";

            $action = "onclick=\"ajaxRequest(
                '{$this->link->app}/api/backend/move.php?database={$this->table->database}&table={$this->table->name}&id={$this->rowId}&action=$type',
                reloadDataTable, 
                '#wi-table'
            )\"";

            $button = "<a class='bi bi-chevron-$type text-dark' $action role='button'></a>";

            if ($info['visible']) {
                if ($type == 'up' && $this->line > 1) {
                    $RETURN = $button;
                } else if ($type == 'down' && $this->line < $info['lines']) {
                    $RETURN = $button;
                }
            }

            return $RETURN;

        }

        public function setValue($format) {

            $VALUE = "";
            $CLASS = "";

            # Set value
                $value = isset($format['value']) ? $format['value'] : '';
                    
                if (!empty($value)) {

                    if (is_array($value)) {

                        $COLUMN_VALUE = "";

                        foreach ($value as $key => $v) {
                            if (sqlColumnExists($this->table->name, $v)) {
                                $COLUMN_VALUE .= $this->row[$v].' ';
                            } else {
                                $COLUMN_VALUE .= $v.' ';
                            }
                        }

                        $COLUMN_VALUE = substr($COLUMN_VALUE, 0, -1);

                    }else{

                        $COLUMN_VALUE = $this->row[$value];

                    }

                } else {

                    $COLUMN_VALUE = empty($this->row[$this->column]) ? "" : $this->row[$this->column];

                }

            # Set value from function
                if (isset($format['function']) && !empty($format['function'])) {

                    $functionName = $format['function']['name'];
                    $functionParameter = isset($format['function']['parameter']) ? $format['function']['parameter'] : 'id';

                    if ($functionName == "empty") {

                        $FUNCTION = isEmpty(
                            $format['function']['tables'], 
                            $format['function']['column'], 
                            $this->rowId, 
                            isset($format['function']['multiple']) ? $format['function']['multiple'] : false
                        );

                        $VALUE = $FUNCTION->icon;

                        if (!$FUNCTION->return) { $this->deleteButton = false; }

                    } elseif ($functionName == "permissions" || $functionName == "permissionsBackend" || $functionName == "permissionsFrontend") {

                        $COLUMN_VALUE = json_decode($COLUMN_VALUE, true);
                        $functionReturn = $format['function']['return'];

                        foreach ($COLUMN_VALUE as $key => $value) {
                            $v = call_user_func_array($functionName, [$value]);
                            if (is_object($v)) { $VALUE .= $v->$functionReturn; }
                        }
                        
                    } else if ($functionName == "active" || $functionName == "visible") {

                        $functionReturn = $format['function']['return'];

                        if ($this->table->name == 'user') {
                            if (count(json_decode($this->row['area'], true)) <= 1 || count(json_decode($this->row['authority'], true)) <= 1) {
                                $VALUE = call_user_func_array($functionName, [$COLUMN_VALUE, $this->rowId])->$functionReturn; 
                            }
                        } else {
                            $VALUE = call_user_func_array($functionName, [$COLUMN_VALUE, $this->rowId])->$functionReturn; 
                        }

                    } else {

                        $args = [];

                        if (is_array($functionParameter)) {
                            foreach ($functionParameter as $parameter) {
                                if (isset($this->row[$parameter])) {
                                    array_push($args, $this->row[$parameter]);
                                } else {
                                    array_push($args, $parameter);
                                }
                            }
                        } else {
                            array_push($args, $this->row[$functionParameter]);
                        }

                        if (isset($format['function']['return']) && !empty($format['function']['return'])) {
                            $functionReturn = $format['function']['return'];
                            $VALUE = call_user_func_array($functionName, $args)->$functionReturn;
                        } else {
                            $VALUE = call_user_func_array($functionName, $args);
                        }

                    }

                } else {

                    $VALUE = $COLUMN_VALUE;
                    
                }

            # Set format
                if (isset($format['format']) && !empty($format['format'])) {

                    $type = $format['format'];

                    if ($type == 'image') {

                        if (!isset($format['function'])) {

                            $VALUE = empty($VALUE) ? [] : json_decode($VALUE);

                            $imageDir = isset($this->table->field[$this->column]['input']['format']['dir']) ? $this->table->field[$this->column]['input']['format']['dir'] : '/';

                            if (isset($this->table->field[$this->column]['input']['format']['resize'])) {

                                $imageResize = $this->table->field[$this->column]['input']['format']['resize'];

                                if (isset($imageResize['width']) && isset($imageResize['height'])) {

                                    $imageSize = $imageResize['width'].'x'.$imageResize['height'].'-';

                                } else {

                                    $s = 10000000;

                                    foreach ($imageResize as $key => $size) {
                                        if ($size['width'] < $s) {
                                            $s = $size['width'];
                                            $imageSize = $size['width'].'x'.$size['height'].'-';
                                        }
                                    }

                                }

                            } else {

                                $imageSize = "";

                            }

                            $VALUE = isset($VALUE[0]) ? $this->link->upload.$imageDir.$imageSize.$VALUE[0] : "";
                            
                        }

                        $VALUE = "<img src='$VALUE' class='img-thumbnail object-fit-contain' style='max-width: calc(((61.5px - 1rem) / 2) * 3) !important;width: calc(((61.5px - 1rem) / 2) * 3) !important; height: calc(61.5px - 1rem) !important;'>";
                    
                    } else if ($type == 'date') {
                        $VALUE = date('d/m/Y', strtotime($VALUE));
                    }

                }

            # Set link to value
                if (isset($format['href']) && !empty($format['href'])) {

                    $href = $format['href'];

                    if ($href == 'modify') {
                        $href = $this->link->modify;
                    } elseif ($href == 'view') {
                        $href = $this->link->view;
                    } elseif ($href == 'mailto') {
                        $href = "mailto:$VALUE";
                    } elseif ($href == 'tel') {
                        $href = "tel:$VALUE";
                        $VALUE = prettyPhone($VALUE);
                    }

                    $VALUE = "<a href='$href' class='fw-semibold text-dark'>".$VALUE."</a>";

                }

            # Column resize
            
                $phone = isset($format['phone']) ? $format['phone'] : true;
                $tablet = isset($format['tablet']) ? $format['tablet'] : true;
                $pc = isset($format['pc']) ? $format['pc'] : true;
                    
                if (!$phone) { $CLASS .= 'phone-none '; }
                if (!$tablet) { $CLASS .= 'tablet-none '; }
                if (!$pc) { $CLASS .= 'pc-none '; }

            # Column size

                $dimension = !empty($format['dimension']) ? $format['dimension'] : '';

                if (empty($dimension)) {
                    if ($this->column == 'authority' || $this->column == 'active' || $this->column == 'visible' || $this->column == 'empty') {
                        $dimension = 'little';
                    }
                }

                $CLASS .= $dimension;

            return $VALUE;

        }

    }