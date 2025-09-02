<?php

    function arrayDetails($array, $key = null) {

        if ($key == null) {
            
            $RETURN = [];

            foreach ($array as $key => $value) { $RETURN[$key] = $value; }

        } else {

            $RETURN = (object) array();

            foreach ($array[$key] as $k => $v) { $RETURN->$k = $v; }

            $RETURN->color = isset($RETURN->color) ? $RETURN->color : '';
            $RETURN->name = isset($RETURN->name) ? $RETURN->name : '';
            $RETURN->text = isset($RETURN->text) ? $RETURN->text : $RETURN->name;
            $RETURN->classIcon = isset($RETURN->icon) ? $RETURN->icon : '';

            $RETURN->icon = empty($RETURN->classIcon) ? "" : "<i class='$RETURN->classIcon'></i>";
            $RETURN->tooltip = empty($RETURN->classIcon) || empty($RETURN->text) ? "" : "<i class='$RETURN->classIcon' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='$RETURN->text'></i>";
            $RETURN->badge = empty($RETURN->color) || empty($RETURN->name) ? "" : "<span class='badge text-bg-$RETURN->color'>".strtoupper($RETURN->name)."</span>";
            $RETURN->badgeTooltip = empty($RETURN->color) || empty($RETURN->text) || empty($RETURN->icon) ? "" : "<span class='badge text-bg-$RETURN->color' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='$RETURN->text'>$RETURN->icon</span>";
            $RETURN->badgeIcon = empty($RETURN->color) || empty($RETURN->text) || empty($RETURN->icon) ? "" : "<span class='badge text-bg-$RETURN->color'  data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='$RETURN->text'>$RETURN->icon</span>";
            $RETURN->automaticResize = empty($RETURN->color) || empty($RETURN->icon) || empty($RETURN->name) ? "" : "<span class='badge text-bg-$RETURN->color'><span class='pc-none'>$RETURN->icon</span><span class='phone-none'>".strtoupper($RETURN->name)."</span></span>";

        }

        return $RETURN;

    }

    function attributeSearchClass($attribute) {

        $classValue = "";

        if ($attribute != null && strpos($attribute, "class") !== false) { 

            if (preg_match('/"([^"]+)"/', $attribute, $r)) {
                $result = $r[1];
            } elseif (preg_match("/'([^']+)'/", $attribute, $r)) {
                $result = $r[1];
            }

            $classValue = strpos($result, "class=") ? "" : $result;   

        }

        return $classValue;

    }

    function badgeSVG($svg, $color, $border = false, $width = 40, $height = 24 )
    {

        $border = $border ? '.5px solid #eeeeee;' : 'none';

        $svg = preg_replace('/<svg\b(?![^>]*\bclass=)/', '<svg class="p-r f-start w-100 h-100 bg-contain"', $svg, 1);
        
        return "
        <span class=\"badge tx-white box-border o-hidden\" style=\"border: none;background: {$color};width: {$width}px;height: {$height}px;border: {$border}\">
            $svg
        </span>";


    }
