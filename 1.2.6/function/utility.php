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
            $RETURN->text = isset($RETURN->text) ? $RETURN->text : '';
            $RETURN->classIcon = isset($RETURN->icon) ? $RETURN->icon : '';

            $RETURN->icon = "<i class='$RETURN->classIcon'></i>";
            $RETURN->tooltip = "<i class='$RETURN->classIcon' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='$RETURN->text'></i>";
            $RETURN->badge = "<span class='badge text-bg-$RETURN->color'>".strtoupper($RETURN->name)."</span>";
            $RETURN->badgeTooltip = "<span class='badge text-bg-$RETURN->color' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='$RETURN->text'>$RETURN->icon</span>";
            $RETURN->badgeIcon = "<span class='badge text-bg-$RETURN->color'>$RETURN->icon</span>";
            $RETURN->automaticResize = "<span class='badge text-bg-$RETURN->color'><span class='pc-none'>$RETURN->icon</span><span class='phone-none'>".strtoupper($RETURN->name)."</span></span>";

        }

        return $RETURN;

    }

?>