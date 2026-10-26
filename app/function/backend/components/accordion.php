<?php

    // Mostra array, oggetti e stringhe JSON in un <pre> leggibile.
    function wiPreIfArray($content)
    {
        
        if (is_array($content) || is_object($content)) {

            return '<pre class="m-0">'.json_encode($content, JSON_PRETTY_PRINT).'</pre>';

        } elseif (is_string($content)) {

            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return '<pre class="m-0">'.json_encode($decoded, JSON_PRETTY_PRINT).'</pre>';
            }

        }

        return $content;

    }
    