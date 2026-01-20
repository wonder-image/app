<?php

    function checkVatFormat(string $vat, ?string $country = null): object
    {

        return Wonder\Plugin\Custom\Tax\Check::vat($vat, $country);

    }

    function checkTinFormat(string $tin, string $country, ?string $type = null): object
    {

        return Wonder\Plugin\Custom\Tax\Check::tin($tin, $country, $type);

    }
