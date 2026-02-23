<?php

namespace Wonder\Plugin\Custom\Tax;

class Check
{
    public static function vat(string $vat, ?string $country = null): object
    {
        return \Wonder\Data\Validators\VatValidator::vat($vat, $country);
    }

    public static function tin(string $tin, string $country, ?string $type = null): object
    {
        return \Wonder\Data\Validators\TinValidator::tin($tin, $country, $type);
    }
}
