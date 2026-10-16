<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormField;

class SchedulerPageSchema extends CustomPageSchema
{
    public static function periodField(int $days): \Wonder\App\ResourceSchema\Input
    {
        return FormField::key('days')->select([7 => '7 giorni', 30 => '30 giorni', 90 => '90 giorni', 180 => '180 giorni'], 'old')->value($days)->label('Periodo');
    }
}
