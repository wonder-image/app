<?php

    function wiCard(mixed $content)
    {

        $content = wiPreIfArray($content);

        return <<<HTML
        <wi-card>
            {$content}
        </wi-card>
        HTML;

    }

    function wiCardLink($text, $link, $color, $icon = null)
    {

        $iconHTML = "";

        if (!is_null($icon)) {
            $iconHTML = "<div class='card-title fs-1'> <i class='bi bi-{$icon}'></i></div>";
        }

        return <<<HTML
        <a href="{$link}" class="card text-center text-{$color}-emphasis text-decoration-none bg-{$color}-subtle border-{$color}-subtle">
            <div class="card-body">
                {$iconHTML}
                <div class="card-text"> {$text} </div>
            </div>
        </a>
        HTML;
        
    }

    function wiCardStats($title, $value, $unit = '', $oldValue = null)
    {

        if ($oldValue === null) {

            return <<<HTML
            <wi-card>
                <div class="col-12">
                    <h6 class="text-muted">{$title}</h6>
                </div>
                <div class="col-12">
                    <h2 class="w-auto mb-0">{$value}{$unit}</h2>
                </div>
            </wi-card>
            HTML;

            
        } else {

            $increment = (($value / $oldValue) - 1) * -100;

            if ($increment < 10 && $increment > -10) {
                $increment = create_number($increment, 2);
            } else {
                $increment = create_number($increment);
            }

            if ($value > $oldValue) {
                $incrementIcon = '<i class="bi bi-arrow-up"></i>';
                $incrementColor = 'success';
            } else if ($value < $oldValue) {
                $incrementIcon = '<i class="bi bi-arrow-down"></i>';
                $incrementColor = 'danger';
            } else if ($value == $oldValue) {
                $incrementIcon = '<i class="bi bi-slash"></i>';
                $incrementColor = 'muted';
            }

            return <<<HTML
            <wi-card>
                <div class="col-12">
                    <h6>{$title}</h6>
                </div>
                <div class="col-8">
                    <h2 class="w-auto mb-0">{$value}{$unit}</h2>
                </div>
                <div class="col-4">
                    <h6 class="mb-0 text-end align-middle text-{$incrementColor}" data-bs-toggle="tooltip" data-bs-title="{$oldValue}{$unit}">{$incrementIcon}{$increment}%</h6>
                </div>
            </wi-card>
            HTML;

        }

    }

    function wiCardAccordion(string $title, mixed $content, bool $expanded = false)
    {

        $content = wiPreIfArray($content);
        
        $ariaExpanded = $expanded ? 'true' : 'false';
        $show = $expanded ? 'show' : '';

        $id = code('5', 'letters', 'accordion_');

        return wiCard(<<<HTML
            <h6 class="col-12 d-flex justify-content-between align-items-center text-start" data-bs-toggle="collapse" data-bs-target="#{$id}" aria-expanded="{$ariaExpanded}" style="cursor: pointer;">
                <span class="mb-0">{$title}</span>
                <span class="chevron"> <i class="bi bi-chevron-down"></i> </span>
            </h6>
            <div id="{$id}" class="collapse {$show} col-12">{$content}</div>
        HTML);

    }