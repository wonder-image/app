<?php

    function checkVatFormat(string $vat, ?string $country = null): object
    {

        return Wonder\Data\Validators\VatValidator::vat($vat, $country);

    }

    function checkTinFormat(string $tin, string $country, ?string $type = null): object
    {

        return Wonder\Data\Validators\TinValidator::tin($tin, $country, $type);

    }
