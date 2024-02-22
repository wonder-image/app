<?php

    namespace Wonder\Table;

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
            $this->table->folder = $TABLE->folder;

            $this->link = (object) array();
            $this->link->site = $PATH->site;
            $this->link->backend = $PATH->backend;
            $this->link->app = $PATH->app;
            $this->link->folder = $this->link->backend.'/'.$this->table->folder;

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

        }

        public function newField($row, $column = null, $format = null) {

            $this->row = $row;
            $this->column = $column;

            $this->rowId = $row['id'];

            $this->link->view = $this->link->folder.'/view.php?redirect='.$this->redirectBase64.'&id='.$this->rowId;
            $this->link->modify = $this->link->folder.'/?redirect='.$this->redirectBase64.'&modify='.$this->rowId;
            $this->link->download = $this->link->folder.'/download.php?id='.$this->rowId;

            if ($format != null) {
                if ($column == 'action_button') {

                    return $this->actionButton($format);

                } else {

                    return $this->setValue($format);

                }

            }

        }

        private function deleteRow() {

            $action = "onclick=\"modal(
                'Sei sicuro di voler eliminare {$this->text->this} {$this->text->titleS}?',
                '{$this->link->app}/api/backend/delete.php?table={$this->table->name}&id={$this->rowId}',
                'ATTENZIONE',
                'Elimina',
                'danger',
                'Chiudi',
                'dark',
                'reloadDataTable', 
                '#wi-table'
            )\"";

            return returnButton("Elimina", $action, 'danger', true);

        }

        private function deleteAuthority() {

            $action = "onclick=\"modal(
                'Sei sicuro di voler eliminare {$this->text->this} {$this->text->titleS}?',
                '{$this->link->app}/api/backend/authority.php?table={$this->table->name}&id={$this->rowId},
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

            return returnButton("Elimina", $action, 'danger', true);

        }

        private function changeVisible() {

            $action = "onclick=\"ajaxRequest(
                '{$this->link->app}/api/backend/active.php?table={$this->table->name}&id={$this->rowId}',
                reloadDataTable, 
                '#wi-table'
            )\"";

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
                (array) returnButton($textButton, $action)
            );

        }

        private function changeActive() {

            $action = "onclick=\"ajaxRequest(
                '{$this->link->app}/api/backend/active.php?table={$this->table->name}&id={$this->rowId}',
                reloadDataTable, 
                '#wi-table'
            )\"";

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
                (array) returnButton($textButton, $action)
            );

        }

        public function actionButton($actionArray) {

            if (!empty($actionArray)) {

                $BUTTONS = "";
                
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

                        $label = $link['label'];
                        $href = isset($link['href']) ? $link['href'] : '';
                        $action = isset($link['action']) ? $link['action'] : '';
                        $target = isset($link['target']) ? 'target="'.$link['target'].'"' : '';

                        if (!empty($href)) {
                            preg_match_all('/{(.*?)}/', $href, $listVar);
                            if (count($listVar) > 0) {
                                foreach ($listVar[1] as $key => $var) {
                                    $href = str_replace('{'.$var.'}', $this->row[$var], $href);
                                }
                            }
                        }

                        if (isset($link['key']) && !empty($link['key'])) {
                            foreach ($link['key'] as $q) {

                                if ($q == 'table') {
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
                        }

                        if (!empty($href)) { $action = 'href="'.$href.'"'; }

                        $BUTTONS .= "<a class='dropdown-item' $action role='button' $target>$label</a>";

                    }

                }

                if (!empty($BUTTONS)) {
                    
                    return "
                    <div class='btn-group position-static'>
                        <span class='badge text-dark' role='button' data-bs-toggle='dropdown' aria-bs-haspopup='true' aria-bs-expanded='false'>
                            <i class='bi bi-three-dots'></i>
                        </span>
                        <div class='dropdown-menu dropdown-menu-right position-fixed'>
                            $BUTTONS
                        </div>
                    </div>";

                }

            }

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

                        $FUNCTION = isEmpty($format['function']['tables'], $format['function']['column'], $this->rowId, isset($format['function']['multiple']) ? $format['function']['multiple'] : false);
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
                        $VALUE = "<img src='$VALUE' class='img-thumbnail object-fit-cover' style='max-width: calc(((61.5px - 1rem) / 2) * 3) !important;width: calc(((61.5px - 1rem) / 2) * 3) !important; height: calc(61.5px - 1rem) !important;'>";
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

    // if ($FILTER->arrow && $FILTER->selected_lines > 1) {

    //     $onclickUp = "<a class='bi bi-chevron-up text-dark' onclick=\"ajaxRequest('$PATH->app/api/backend/move.php?table=$table->name&id=$ROW_ID&action=up')\" role='button'></a>";
    //     $onclickDown = "<a class='bi bi-chevron-down text-dark' onclick=\"ajaxRequest('$PATH->app/api/backend/move.php?table=$table->name&id=$ROW_ID&action=down')\" role='button'></a>";

    //     if ($lineN == 1) {
    //         echo "<td scope='col' class='phone-none little'></td>";
    //         echo "<td scope='col' class='phone-none little'>$onclickDown</td>";
    //     } elseif ($lineN == $FILTER->selected_lines) {
    //         echo "<td scope='col' class='phone-none little'>$onclickUp</td>";
    //         echo "<td scope='col' class='phone-none little'></td>";
    //     } else {
    //         echo "<td scope='col' class='phone-none little'>$onclickUp</td>";
    //         echo "<td scope='col' class='phone-none little'>$onclickDown</a></td>";
    //     }
    // }

?>