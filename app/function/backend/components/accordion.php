<?php

    function wiAccordion(string $title, mixed $content, bool $expanded = false)
    {

        $content = wiPreIfArray($content);

        $ariaExpanded = $expanded ? 'true' : 'false';
        $collapsed = $expanded ? '' : 'collapsed';
        $show = $expanded ? 'show' : '';

        $id = code('5', 'letters', 'accordion_');

        return <<<HTML
            <div class="accordion">
                <div class="accordion-item">
                    <div class="accordion-header">
                        <button class="accordion-button {$collapsed}" type="button" data-bs-toggle="collapse" data-bs-target="#{$id}" aria-expanded="{$ariaExpanded}" aria-controls="{$id}"> {$title} </button>
                    </div>
                    <div id="{$id}" class="accordion-collapse collapse {$show}">
                        <div class="accordion-body"> {$content} </div>
                    </div>
                </div>
            </div>
        HTML;

    }

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
    