<?php

    function paginationButton($text, $url, $style, $active = false, $disabled = false) {

        $href = $disabled ? '' : 'href="'.$url.'"';

        $class = $style['global'].' ';
        $class .= (!$disabled && !$active) ? $style['normal'] : '';
        $class .= ($active) ? $style['active'] : '';
        $class .= ($disabled) ? $style['disabled'] : '';

        return '<a '.$href.' class="'.$class.'">'.$text.'</a>';

    }

    function pagination($table, $query, $row, $scroll = null, $style = [
            'container' => 'w-100 j-content-center d-flex gap-3',
            'button' => [
                'global' => 'btn',
                'normal' => 'btn-secondary-o',
                'active' => 'btn-secondary',
            ],
            'arrow' => [
                'global' => 'btn',
                'normal' => 'btn-primary',
                'disabled' => 'btn-primary-o',
            ]
        ]) {

        global $_GET;
        global $PAGE;
        global $PATH;

        $RETURN = (object) array();
        $RETURN->page = isset($_GET['page']) ? $_GET['page'] : 1;
        $RETURN->max_row = sqlCount($table, $query);
        $RETURN->max_page = ceil($RETURN->max_row / $row);

        $RETURN->page = ($RETURN->page > $RETURN->max_page) ? $RETURN->max_page : $RETURN->page;
        
        $RETURN->html = "<div class='d-flex gap-3'>";

        $url = parse_url($PAGE->url);
        $href = $PATH->site.$url['path'];

        if (empty($url['query'])) {

            $href .= ($scroll == null) ? '?page=' : '?scroll='.$scroll.'&page=';

        } else {
            
            parse_str($url['query'], $query);
            
            unset($query['page']);
            unset($query['scroll']);

            $href .= empty($query) ? '?' : '?'.http_build_query($query).'&';
            $href .= ($scroll == null) ? 'page=' : 'scroll='.$scroll.'&page=';

        }

        $classContainer = $style['container'];
        $classButton = $style['button'];
        $classArrow = $style['arrow'];

        $RETURN->html = "<div class='$classContainer'>";

        if ($RETURN->max_page == 1) {

            $RETURN->html .= paginationButton(1, $href.'1', $classButton, true);

        } else {

            $PAGE_AFTER = $RETURN->page + 1;
            $PAGE_BEFORE = $RETURN->page - 1;

            $disabled = ($RETURN->page == 1) ? true : false;
            $RETURN->html .= paginationButton('<i class="bi bi-chevron-left"></i>', $href.$PAGE_BEFORE, $classArrow, false, $disabled);

            if ($RETURN->max_page >= 2 && $RETURN->max_page <= 5) {
                for ($i=1; $i <= $RETURN->max_page; $i++) { 
                    $active = ($i == $RETURN->page) ? true : false;
                    $RETURN->html .= paginationButton($i, $href.$i, $classButton, $active);
                }
            } else {

                if ($RETURN->page <= 3) {

                    for ($i=1; $i <= 4; $i++) { 
                        $active = ($i == $RETURN->page) ? true : false;
                        $RETURN->html .= paginationButton($i, $href.$i, $classButton, $active);
                    }

                    $RETURN->html .= paginationButton($RETURN->max_page, $href.$RETURN->max_page, $classButton, false);

                } else if ($RETURN->page >= $RETURN->max_page - 2) {

                    $RETURN->html .= paginationButton(1, $href.'1', $classButton, false);

                    for ($i=$RETURN->max_page - 3; $i <= $RETURN->max_page; $i++) { 
                        $active = ($i == $RETURN->page) ? true : false;
                        $RETURN->html .= paginationButton($i, $href.$i, $classButton, $active);
                    }

                } else {

                    $RETURN->html .= paginationButton(1, $href.'1', $classButton, false);

                    $RETURN->html .= paginationButton($RETURN->page - 1, $href.$RETURN->page - 1, $classButton, false);
                    $RETURN->html .= paginationButton($RETURN->page, $href.$RETURN->page, $classButton, true);
                    $RETURN->html .= paginationButton($RETURN->page + 1, $href.$RETURN->page + 1, $classButton, false);

                    $RETURN->html .= paginationButton($RETURN->max_page, $href.$RETURN->max_page, $classButton, false);

                }

            }

            $disabled = ($RETURN->page == $RETURN->max_page) ? true : false;
            $RETURN->html .= paginationButton('<i class="bi bi-chevron-right"></i>', $href.$PAGE_AFTER, $classArrow, false, $disabled);

        }

        $RETURN->html .= "</div>";

        $RETURN->limit = ($RETURN->page == 1) ? "0, $row" : $row * ($RETURN->page - 1).", $row";

        return $RETURN;

    }

?>