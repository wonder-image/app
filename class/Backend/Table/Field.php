<?php

    namespace Wonder\Backend\Table;

    use Wonder\Support\Prettify\Phone;
    use DateTime;
    use Wonder\App\Support\CssFontFamily;
    
    class Field {

        private $table;
        private $customLinkOriginal; # Link custom originali
        private $customLink; # Link custom modificati per ogni linea
        private $link;
        private $text;
        private $user;


        private $row;
        private $rowId;
        private $column;

        private $function; # Cache per ogni funzione chiamata ( Ogni nuova riga viene inizializzata )

        private $redirect;        
        private $redirectBase64;

        public $line = 0;
        private $deleteButton = true;


        /**
         * 
         * Alcune funzioni di questa classe sono esterne come:
         *  - returnButton()
         *  - isEmpty()
         * 
         */


        public function __construct(object $TABLE, object $PATH, object $TEXT, object $USER, object $PAGE) { 

            $this->table = (object) [];
            $this->table->id = $TABLE->id;
            $this->table->name = $TABLE->table;
            $this->table->connection = $TABLE->connection;
            $this->table->database = $TABLE->database;
            $this->table->field = $TABLE->field;
            $this->table->page = $TABLE->page;
            $this->table->length = $TABLE->length;

            $this->customLink = (object) [];
            $this->customLinkOriginal = (object) [];
            foreach ($TABLE->link as $key => $link) { $this->customLinkOriginal->$key = $link; }

            $this->link = (object) [];
            $this->link->site = $PATH->site;
            $this->link->backend = $PATH->backend;
            $this->link->app = $PATH->app;
            $this->link->api = $PATH->api;

            $this->text = (object) [];
            $this->text->titleS = $TEXT->titleS;
            $this->text->titleP = $TEXT->titleP;
            $this->text->last = $TEXT->last;
            $this->text->all = $TEXT->all;
            $this->text->article = $TEXT->article;
            $this->text->full = $TEXT->full;
            $this->text->empty = $TEXT->empty;
            $this->text->this = $TEXT->this;

            $this->user = (object) [];
            $this->user->area = $USER->area;
            $this->user->authority = $USER->authority;

            $this->redirect = $PAGE->redirect;
            $this->redirectBase64 = $PAGE->redirectBase64;

            $mysqli = $TABLE->connection;

        }

        public function newField($row, $column = null, $format = null) {

            $this->row = $row;
            $this->column = $column;

            # Se cambia la linea
            if ($this->rowId != $row['id']) { 

                $this->line++; 
            
                $this->function = [];

            }

            if ($this->line == 1) { $this->line = $this->line + ($this->table->page * $this->table->length); }

            $this->rowId = $row['id'];

            foreach ($this->customLinkOriginal as $key => $link) {
                
                $link = str_replace('{redirectBase64}', $this->redirectBase64, $link);
                $link = str_replace('{rowId}', $this->rowId, $link);

                $this->customLink->$key = $link;

            }

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

            foreach ($key as $k => $q) {

                $link = parse_url($url);
                $url .= isset($link['query']) ? '&' : '?';
                $url .= is_numeric($k) ? "$q={$this->row[$q]}" : "$k=$q";

            }


            return "onclick=\"ajaxRequest(
                '$url',
                reloadDataTable, 
                '#".$this->table->id."'
            )\"";

        }

        private function actionButtonItem($text, $action, $bootstrapColor = "", $line = false) {

            return returnButton($text, $action, $bootstrapColor, $line);

        }

        private function deleteRow() {

            $action = "onclick=\"modal(
                'Sei sicuro di voler eliminare {$this->text->this} {$this->text->titleS}?',
                '{$this->link->api}/backend/delete/?database={$this->table->database}&table={$this->table->name}&id={$this->rowId}',
                'ATTENZIONE',
                'Elimina',
                'danger',
                'Chiudi',
                'dark',
                'reloadDataTable', 
                '#".$this->table->id."'
            )\"";

            return $this->actionButtonItem("Elimina", $action, 'danger', true);

        }

        private function deleteAuthority() {

            $url = "{$this->link->api}/backend/authority/?database={$this->table->database}&table={$this->table->name}&id={$this->rowId}";

            if ($this->user->authority != '') {
                $url .= "&authority={$this->user->authority}";
            }

            if ($this->user->area != '') {
                $url .= "&area={$this->user->area}";
            }

            $action = "onclick=\"modal(
                'Sei sicuro di voler eliminare {$this->text->this} {$this->text->titleS}?',
                '$url',
                'ATTENZIONE',
                'Elimina',
                'danger',
                'Chiudi',
                'dark',
                'reloadDataTable', 
                '#".$this->table->id."'
            )\"";

            return $this->actionButtonItem("Elimina", $action, 'danger', true);

        }

        private function changeVisible() {

            $action = $this->ajaxRequest("{$this->link->api}/backend/change/boolean/", [ 'column' => 'visible' ]);

            return \Wonder\Backend\Table\Badge\BooleanBadge::visible($this->row['visible'] ?? '')
                ->action($action)
                ->legacyObject();

        }

        private function changeActive() {

            $action = $this->ajaxRequest("{$this->link->api}/backend/change/boolean/", [ 'column' => 'active' ]);

            return \Wonder\Backend\Table\Badge\BooleanBadge::active($this->row['active'] ?? '')
                ->action($action)
                ->legacyObject();

        }

        private function changeEvidence() {

            $action = $this->ajaxRequest("{$this->link->api}/backend/change/boolean/", [ 'column' => 'evidence' ]);

            return \Wonder\Backend\Table\Badge\BooleanBadge::evidence($this->row['evidence'] ?? '')
                ->action($action)
                ->legacyObject();

        }

        public function actionButton($actionArray) {

            $BUTTONS = "";

            if (!empty($actionArray)) {
                
                foreach ($actionArray as $ACTION => $link) {

                    if ($link && !is_array($link) && $link != 'false') {

                        if ($ACTION == 'view') { $BUTTONS .= "<a class='dropdown-item' href='{$this->customLink->view}' role='button'>Visualizza</a>"; }
                        elseif ($ACTION == 'modify') { $BUTTONS .= "<a class='dropdown-item' href='{$this->customLink->modify}' role='button'>Modifica</a>"; }
                        elseif ($ACTION == 'duplicate') { $BUTTONS .= "<a class='dropdown-item' href='{$this->customLink->duplicate}' role='button'>Duplica</a>"; }
                        elseif ($ACTION == 'download') { $BUTTONS .= "<a class='dropdown-item' href='{$this->customLink->download}'  target='_blank' rel='noopener noreferrer' role='button'>Scarica</a>"; }
                        elseif ($ACTION == 'visible') { $BUTTONS .= $this->changeVisible()->button; }
                        elseif ($ACTION == 'evidence') { $BUTTONS .= $this->changeEvidence()->button; }
                        elseif ($ACTION == 'delete' && $this->deleteButton) { $BUTTONS .= $this->deleteRow()->button; }
                        elseif ($ACTION == 'authority' && $this->deleteButton) { 
                            
                            if ($this->table->name == 'user') {
                                
                                $userArea = json_decode($this->row['area'], true);
                                $userAuthority = json_decode($this->row['authority'], true);

                                if (count($userArea) == 1 && count($userAuthority) == 1) {
                                    $BUTTONS .= $this->deleteRow()->button;
                                } else if (!empty($this->user->area) && !empty($this->user->authority)) {
                                    $BUTTONS .= $this->deleteAuthority()->button;
                                }

                            }
                        
                        }
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


                        if ($ACTION == 'view') { $BUTTON = "<a class='dropdown-item' href='{$this->customLink->view}' role='button'>Visualizza</a>"; }
                        else if ($ACTION == 'modify') { $BUTTON = "<a class='dropdown-item' href='{$this->customLink->modify}' role='button'>Modifica</a>"; }
                        else if ($ACTION == 'delete') {  $BUTTON = $this->deleteRow()->button; } 
                        else {

                            if (!empty($href)) {

                                preg_match_all('/{(.*?)}/', $href, $listVar);

                                if (count($listVar) > 0) {
                                    foreach ($listVar[1] as $k => $var) {
                                        $href = str_replace('{'.$var.'}', $this->row[$var] ?? '', $href);
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

                                $action = empty($href) ? '' : 'href="'.$href.'"';

                            } else if (!empty($request)) {

                                if ($request == 'boolean') {
                                    $url = "{$this->link->api}/backend/change/boolean/?column=$ACTION";
                                } else {
                                    $url = $this->link->api.'/'.$request;
                                }

                                $action = $this->ajaxRequest($url, $key);
                                
                            }

                            if (is_array($label)) {
                                $label = isset($label[$this->row[$ACTION]]) ? $label[$this->row[$ACTION]] : '';
                            }
    
                            $BUTTON = empty($label) || empty($action) ? "" : "<a class='dropdown-item' $action role='button' $target>$label</a>";
    
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
                                        if ((!is_array($value) && $this->row[$key] != $value) || (is_array($value) && in_array($this->row[$key], $value) == false)) { 
                                            $public = false; 
                                            break; 
                                        }
                                    }
                                }


                        }

                        $BUTTONS .= ($public) ? $BUTTON : "";

                    }

                }

                if (!empty($BUTTONS)) {

                    $BUTTONS = "
                    <div class='dropdown'>
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
                '{$this->link->api}/backend/move/?database={$this->table->database}&table={$this->table->name}&id={$this->rowId}&action=$type',
                reloadDataTable, 
                '#".$this->table->id."'
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

            # Rimappa i descrittori legacy function(active|visible|evidence) sul
            # percorso badge: niente funzioni globali, niente global $NAME/$PATH.
                if (
                    isset($format['function']['name'])
                    && in_array($format['function']['name'], ['active', 'visible', 'evidence'], true)
                ) {

                    $format['badge'] = [
                        'preset' => $format['function']['name'],
                        'column' => $this->column,
                        'variant' => (isset($format['function']['return']) && !empty($format['function']['return']))
                            ? $format['function']['return']
                            : 'automaticResize',
                        'clickable' => false,
                    ];

                    unset($format['function']);

                }

            # Render badge booleano (API dichiarativa, contesto iniettato — mai global)
                if (isset($format['badge']) && is_array($format['badge'])) {

                    return $this->setBadgeValue($format, $COLUMN_VALUE);

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

                        $this->deleteButton = ($FUNCTION->return) ? true : false;

                    } elseif ($functionName == "permissions" || $functionName == "permissionsBackend" || $functionName == "permissionsFrontend" || $functionName == "permissionsApi") {

                        $COLUMN_VALUE = json_decode($COLUMN_VALUE, true);
                        $functionReturn = $format['function']['return'];

                        foreach ($COLUMN_VALUE as $key => $value) {
                            $v = call_user_func_array($functionName, [$value]);
                            if (is_object($v)) { $VALUE .= $v->$functionReturn; }
                        }
                        
                    } else {

                        # Whitelist server-side: il nome arriva dal POST di
                        # list-table, senza controllo sarebbe una chiamata a
                        # funzione PHP arbitraria.
                        if (
                            !\Wonder\Backend\Table\ColumnFunctionRegistry::isAllowed($functionName)
                            || !function_exists($functionName)
                        ) {

                            $VALUE = '';

                        } else if (isset($this->function[$functionName]) && $this->function[$functionName]['parameter'] == $functionParameter) {

                            # Controllo se è già stata chiamata questa funzione con gli stessi parametri così da non chiamarla due volte

                            if (isset($format['function']['return']) && !empty($format['function']['return'])) {
                                $functionReturn = $format['function']['return'];
                                $VALUE = $this->function[$functionName]['return']->$functionReturn;
                            } else {
                                $VALUE = $this->function[$functionName]['return'];
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

                            $return = call_user_func_array($functionName, $args);

                            if (isset($format['function']['return']) && !empty($format['function']['return'])) {
                                $functionReturn = $format['function']['return'];
                                $VALUE = $return->$functionReturn;
                            } else {
                                $VALUE = $return;
                            }

                            $this->function[$functionName] = [];
                            $this->function[$functionName]['parameter'] = $functionParameter;
                            $this->function[$functionName]['return'] = $return;

                        }

                    }

                } else {

                    $VALUE = $COLUMN_VALUE;

                    if ($this->table->name === 'css_font' && $this->column === 'font_family') {
                        $VALUE = CssFontFamily::normalize($VALUE);
                    }
                    
                }

            # Set format
                if (isset($format['format']) && !empty($format['format'])) {

                    $type = $format['format'];

                    if ($type == 'image') {

                        if (!isset($format['function'])) {

                            $VALUE = empty($VALUE) ? [] : json_decode($VALUE);

                            $imageDir = isset($this->table->field[$this->column]['input']['format']['dir']) ? $this->table->field[$this->column]['input']['format']['dir'] : '/';

                            // Default sicuri: nessuna resize, file servito dal
                            // path nudo. Prima `$sizeBefore` non era
                            // inizializzato nel ramo "no resize" → "Undefined
                            // variable $sizeBefore" a riga ~672 nei resource
                            // dove il field è `text()` ma la colonna è
                            // formattata come `image()` (es. Media.file).
                            $imageSize = '';
                            $sizeBefore = false;

                            if (isset($this->table->field[$this->column]['input']['format']['resize'])) {

                                $imageResize = $this->table->field[$this->column]['input']['format']['resize'];

                                if (isset($imageResize['width'])) {

                                    $imageSize = $imageResize['width'].'x'.($imageResize['height'] ?? $imageResize['width']).'-';
                                    $sizeBefore = true;

                                } else if (isset($imageResize[0]['width'])) {
                                    $s = 10000000;

                                    foreach ($imageResize as $key => $size) {
                                        if ($size['width'] < $s) {
                                            $s = $size['width'];
                                            $imageSize = $size['width'].'x'.($size['height'] ?? $size['width']).'-';
                                        }
                                    }

                                    $sizeBefore = true;

                                } else if (isset($imageResize[0])) {

                                    $imageSize = '-'.$imageResize[0];
                                    $sizeBefore = false;

                                }
                                // Altri pattern di resize non riconosciuti
                                // → defaults sopra (no size suffix).

                            }

                            // `customLink->file` può mancare se l'AJAX non
                            // l'ha inviato (es. table caller legacy senza
                            // addLink('file', ...)). Fall back a stringa
                            // vuota: l'URL sarà relativo al dominio corrente
                            // e l'<img> mostrerà broken se il path è errato,
                            // ma niente PHP warning nel JSON di risposta.
                            $fileBase = $this->customLink->file ?? '';

                            if (isset($VALUE[0])) {
                                if ($sizeBefore) {
                                    $VALUE = $fileBase.$imageDir.$imageSize.$VALUE[0];
                                } else {
                                    $extension = pathinfo($VALUE[0], PATHINFO_EXTENSION);
                                    $name = pathinfo($VALUE[0], PATHINFO_FILENAME);
                                    $VALUE = $fileBase.$imageDir.$name.$imageSize.'.'.$extension;
                                }
                            } else {
                                $VALUE = "";
                            }

                        }

                        $VALUE = "<img src='$VALUE' class='img-thumbnail object-fit-contain' style='max-width: calc(((61.5px - 1rem) / 2) * 3) !important;width: calc(((61.5px - 1rem) / 2) * 3) !important; height: calc(61.5px - 1rem) !important;'>";
                    
                    } else if ($type == 'date') {

                        $VALUE = empty($VALUE) ? "" : (new DateTime($VALUE))->format('d/m/Y');

                    } else if ($type == 'datetime') {

                        $VALUE = empty($VALUE) ? "" : (new DateTime($VALUE))->format('d/m/Y H:i');

                    } else if ($type == 'phone') {

                        $prefix = (isset($this->row['phone_prefix']) && !empty($VALUE)) ? $this->row['phone_prefix']." " : "";
                        $VALUE = $prefix.Phone::prettify($VALUE);

                    } else if ($type == 'price') {

                        $VALUE = empty($VALUE) ? "" : number_format($VALUE, 2, '.', '').'€';

                    } else if ($type == 'color') {

                        if (!empty($VALUE)) {
                            
                            $colorInfo = colorInfo(hexToRgb($VALUE));

                            $VALUE = empty($VALUE) ? "" : '<span class="badge" style="background: '.$VALUE.'; color: '.$colorInfo->neutral->color.'">'.$VALUE.'</span>';

                        }

                    } else if ($type == 'status') {

                        if (!empty($VALUE)) {

                            $bool = filter_var($VALUE, FILTER_VALIDATE_BOOLEAN);
                            
                            if ($bool) {
                                $VALUE = '<span class="badge text-bg-success">SUCCESSO</span>';
                            } else {
                                $VALUE = '<span class="badge text-bg-danger">ERRORE</span>';
                            }

                        }

                    } else if ($type == 'user' || $type == 'user_avatar' || $type == 'user_name') {

                        if (!empty($VALUE)) {

                            $U = infoUser($VALUE);
                            
                            if ($U->exists) {

                                $VALUE = match ($type) {
                                    'user' => '<div class="d-inline-flex"><div class="me-1" style="width: 23px; font-size: 10px">'.$U->avatar . '</div> ' . $U->fullName.'</div>',
                                    'user_avatar' => '<div style="width: 23px; font-size: 10px">'.$U->avatar.'</div>',
                                    'user_name' => $U->fullName,
                                };
                                
                            }
                            
                        }

                    }

                }

            # Set link to value
                if (isset($format['href']) && !empty($format['href'])) {

                    $href = $format['href'];

                    if ($href == 'modify') {
                        $href = $this->customLink->modify;
                    } elseif ($href == 'view') {
                        $href = $this->customLink->view;
                    } elseif ($href == 'mailto') {
                        $href = "mailto:$VALUE";
                    } elseif ($href == 'tel') {

                        $href = "tel:".str_replace(' ', '', $VALUE);
                        $VALUE = Phone::prettify($VALUE);

                    }

                    $VALUE = "<a href='$href' class='fw-semibold text-dark'>".$VALUE."</a>";

                }

            return $VALUE;

        }

        private function setBadgeValue($format, $COLUMN_VALUE) {

            $descriptor = $format['badge'];

            $preset = isset($descriptor['preset']) && is_string($descriptor['preset']) ? trim($descriptor['preset']) : '';
            $column = isset($descriptor['column']) && is_string($descriptor['column']) && trim($descriptor['column']) !== ''
                ? trim($descriptor['column'])
                : $this->column;
            $variant = isset($descriptor['variant']) && is_string($descriptor['variant']) && trim($descriptor['variant']) !== ''
                ? trim($descriptor['variant'])
                : 'automaticResize';

            $value = (is_array($this->row) && array_key_exists($column, $this->row)) ? $this->row[$column] : $COLUMN_VALUE;

            if ($preset !== '') {

                # Special-case tabella user: replica il comportamento del badge
                # legacy — nascosto solo se l'utente ha più di una area E più di
                # una authority. Intenzionalmente diverso dal menu azioni
                # (actionButton), che nasconde con più di una area O authority:
                # divergenza storica confermata in review (2026-07-02).
                if ($this->table->name == 'user') {

                    $areas = json_decode($this->row['area'] ?? '[]', true);
                    $authorities = json_decode($this->row['authority'] ?? '[]', true);

                    if (is_array($areas) && is_array($authorities) && count($areas) > 1 && count($authorities) > 1) {
                        return '';
                    }

                }

                $badge = \Wonder\Backend\Table\Badge\BooleanBadge::preset($preset, $value);

                if ($badge === null) { return ''; }

            } else {

                $on = (array) ($descriptor['on'] ?? []);
                $off = (array) ($descriptor['off'] ?? []);

                $badge = \Wonder\Backend\Table\Badge\BooleanBadge::make($value)
                    ->on((string) ($on['text'] ?? ''), (string) ($on['icon'] ?? ''), (string) ($on['color'] ?? ''), (string) ($on['button'] ?? ''))
                    ->off((string) ($off['text'] ?? ''), (string) ($off['icon'] ?? ''), (string) ($off['color'] ?? ''), (string) ($off['button'] ?? ''));

            }

            $badge->action($this->ajaxRequest("{$this->link->api}/backend/change/boolean/", [ 'column' => $column ]));

            if (!empty($descriptor['clickable'])) { $badge->clickable(); }

            return $badge->render($variant);

        }

    }
