<?php

    function arrayDetails($array, $key = null) {

        if ($key == null) {
            
            $RETURN = [];

            foreach ($array as $key => $value) { $RETURN[$key] = $value['text']; }

        } else {

            $RETURN = (object) array();
            $RETURN->color = $array[$key]['color'];
            $RETURN->name = $array[$key]['name'];
            $RETURN->text = $array[$key]['text'];
            $RETURN->classIcon = $array[$key]['icon'];

            $RETURN->icon = "<i class='$RETURN->classIcon'></i>";
            $RETURN->tooltip = "<i class='$RETURN->classIcon' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='$RETURN->text'></i>";
            $RETURN->badge = "<span class='badge text-bg-$RETURN->bootstrapColor'>$RETURN->name</span>";
            $RETURN->badgeIcon = "<span class='badge text-bg-$RETURN->bootstrapColor'>$RETURN->icon</span>";


        }

        return $RETURN;

    }

?>