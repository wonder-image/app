<?php

namespace Wonder\Support\Barcode;

use InvalidArgumentException;

final class Ean
{
    private const L = [
        '0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101', '4' => '0100011',
        '5' => '0110001', '6' => '0101111', '7' => '0111011', '8' => '0110111', '9' => '0001011',
    ];

    private const G = [
        '0' => '0100111', '1' => '0110011', '2' => '0011011', '3' => '0100001', '4' => '0011101',
        '5' => '0111001', '6' => '0000101', '7' => '0010001', '8' => '0001001', '9' => '0010111',
    ];

    private const R = [
        '0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010', '4' => '1011100',
        '5' => '1001110', '6' => '1010000', '7' => '1000100', '8' => '1001000', '9' => '1110100',
    ];

    private const EAN13_PARITY = [
        '0' => 'LLLLLL', '1' => 'LLGLGG', '2' => 'LLGGLG', '3' => 'LLGGGL', '4' => 'LGLLGG',
        '5' => 'LGGLLG', '6' => 'LGGGLL', '7' => 'LGLGLG', '8' => 'LGLGGL', '9' => 'LGGLGL',
    ];

    /** @return array{code: string, modules: string} */
    public static function encode(string $value, int $length): array
    {
        if (!in_array($length, [8, 13], true)) {
            throw new InvalidArgumentException('Sono supportati solo EAN-8 ed EAN-13.');
        }

        $value = trim($value);

        if (preg_match('/^\d+$/', $value) !== 1 || !in_array(strlen($value), [$length - 1, $length], true)) {
            throw new InvalidArgumentException("EAN-{$length} deve contenere ".($length - 1)." o {$length} cifre.");
        }

        $body = substr($value, 0, $length - 1);
        $checkDigit = self::checkDigit($body);

        if (strlen($value) === $length && $value[$length - 1] !== $checkDigit) {
            throw new InvalidArgumentException("Checksum EAN-{$length} non valido.");
        }

        $code = $body.$checkDigit;

        return [
            'code' => $code,
            'modules' => $length === 13 ? self::ean13Modules($code) : self::ean8Modules($code),
        ];
    }

    public static function checkDigit(string $body): string
    {
        if (preg_match('/^\d+$/', $body) !== 1 || !in_array(strlen($body), [7, 12], true)) {
            throw new InvalidArgumentException('Il corpo EAN deve contenere 7 o 12 cifre.');
        }

        $sum = 0;
        $weight = 3;

        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    public static function modulesToRuns(string $modules): string
    {
        if ($modules === '' || $modules[0] !== '1' || preg_match('/^[01]+$/', $modules) !== 1) {
            throw new InvalidArgumentException('Pattern EAN non valido.');
        }

        $runs = '';
        $current = $modules[0];
        $count = 0;

        foreach (str_split($modules) as $module) {
            if ($module === $current) {
                $count++;
                continue;
            }

            $runs .= (string) $count;
            $current = $module;
            $count = 1;
        }

        return $runs.(string) $count;
    }

    private static function ean13Modules(string $code): string
    {
        $modules = '101';
        $parity = self::EAN13_PARITY[$code[0]];

        for ($index = 1; $index <= 6; $index++) {
            $modules .= $parity[$index - 1] === 'L' ? self::L[$code[$index]] : self::G[$code[$index]];
        }

        $modules .= '01010';

        for ($index = 7; $index <= 12; $index++) {
            $modules .= self::R[$code[$index]];
        }

        return $modules.'101';
    }

    private static function ean8Modules(string $code): string
    {
        $modules = '101';

        for ($index = 0; $index <= 3; $index++) {
            $modules .= self::L[$code[$index]];
        }

        $modules .= '01010';

        for ($index = 4; $index <= 7; $index++) {
            $modules .= self::R[$code[$index]];
        }

        return $modules.'101';
    }
}
