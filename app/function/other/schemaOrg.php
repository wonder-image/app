<?php

    function breadcrumb($list, $script = true) {

        $RETURN = $script ? '<script type="application/ld+json">'."\n" : '';

        $RETURN .= '{'."\n";
        $RETURN .= $script ? '    "@context": "https://schema.org/",'."\n" : '';
        $RETURN .= '    "@type": "BreadcrumbList",'."\n";
        $RETURN .= '    "itemListElement": [';

        $i = 1;

        foreach ($list as $url => $name) {

            $RETURN .= '{'."\n";
            $RETURN .= '        "@type": "ListItem",'."\n";
            $RETURN .= '        "position": '.$i.','."\n";
            $RETURN .= '        "item": {'."\n";
            $RETURN .= '            "@id": "'.$url.'",'."\n";
            $RETURN .= '            "name": "'.$name.'"'."\n";
            $RETURN .= '        }'."\n";
            $RETURN .= '},';

            $i++;

        }

        $RETURN = substr($RETURN, 0, -1)."]\n";

        $RETURN .= '}'."\n";
        $RETURN .= $script ? '</script>' : '';

        return $RETURN;

    }