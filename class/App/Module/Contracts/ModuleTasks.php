<?php

namespace Wonder\App\Module\Contracts;

interface ModuleTasks
{
    /** @return iterable<\Wonder\App\Scheduler\Contracts\TaskInterface> */
    public static function tasks(): iterable;
}
