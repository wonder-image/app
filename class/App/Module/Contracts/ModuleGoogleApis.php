<?php

namespace Wonder\App\Module\Contracts;

interface ModuleGoogleApis
{
    /** @return iterable<\Wonder\App\Support\GoogleCloud\GoogleApi> */
    public static function googleApis(): iterable;
}
