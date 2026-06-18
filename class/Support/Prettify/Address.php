<?php

namespace Wonder\Support\Prettify;

class Address
{
    public static function prettify($street, $number, $cap, $city, $province, $country, $more = "", $name = "", $surname = "", $phone = ""): object
    {
        $return = (object) [];

        $addressMore = empty($more) ? "" : "<br>$more";
        $addressMorePDF = empty($more) ? "" : "\n$more";

        $number = empty($number) ? "" : " $number";

        $return->line = "$street$number, $cap $city ($province)";
        $prettyPhone = empty($phone) ? "" : Phone::prettify($phone);

        if (!empty($name) && !empty($surname) && !empty($phone)) {
            $return->pretty = "
                <b>$name $surname</b><br>
                $prettyPhone<br>
                $street$number, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$name $surname\n$prettyPhone\n$street$number, $cap\n$city ($province)$addressMorePDF";
        } elseif (!empty($name) && !empty($surname)) {
            $return->pretty = "
                <b>$name $surname</b><br>
                $street$number, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$name $surname\n$street$number, $cap\n$city ($province)$addressMorePDF";
        } elseif (!empty($name)) {
            $return->pretty = "
                <b>$name</b><br>
                $street$number, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$name\n$street$number, $cap\n$city ($province)$addressMorePDF";
        } elseif (!empty($street) && !empty($number) && !empty($cap) && !empty($city) && !empty($province)) {
            $return->pretty = "
                $street$number, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$street$number, $cap\n$city ($province)$addressMorePDF";
        } elseif (!empty($street) && !empty($cap) && !empty($city) && !empty($province)) {
            $return->pretty = "
                $street, $cap <br>
                $city ($province)$addressMore";

            $return->prettyPDF = "$street, $cap\n$city ($province)$addressMorePDF";
        } elseif (!empty($street) && !empty($city) && !empty($province)) {
            $return->pretty = "$street, $city ($province)";
            $return->prettyPDF = "$street, $city ($province)";
        } else {
            $return->line = "--";
            $return->pretty = "--";
            $return->prettyPDF = "--";
        }

        return $return;
    }

    public static function prettifyRow(array $row, ?string $prefix = null, string $mode = 'simple'): object
    {
        $values = self::extractValues($row, $prefix);
        $type = strtolower(trim((string) ($values['type'] ?? '')));

        if ($mode === 'billing' || in_array($type, ['private', 'business'], true)) {
            return self::prettifyTyped($values);
        }

        $address = self::prettify(
            $values['street'],
            $values['number'],
            $values['cap'],
            $values['city'],
            $values['province'],
            $values['country'],
            $values['more'],
            $values['name'],
            $values['surname'],
            self::fullPhone($values['phone_prefix'], $values['phone'])
        );

        $address->prettyPhone = self::prettyPhone($values['phone_prefix'], $values['phone']);

        return $address;
    }

    private static function prettifyTyped(array $values): object
    {
        $return = (object) [
            'line' => self::addressLine(
                $values['street'],
                $values['number'],
                $values['cap'],
                $values['city'],
                $values['province']
            ),
            'prettyPhone' => self::prettyPhone($values['phone_prefix'], $values['phone']),
        ];

        $type = strtolower(trim((string) ($values['type'] ?? '')));
        $heading = trim(match ($type) {
            'business' => (string) ($values['business_name'] ?? ''),
            default => trim($values['name'].' '.$values['surname']),
        });

        $htmlLines = [];
        $pdfLines = [];

        if ($heading !== '') {
            $htmlLines[] = '<b>'.$heading.'</b>';
            $pdfLines[] = $heading;
        }

        if ($type === 'business') {
            foreach (self::businessFiscalLines($values['pi'], $values['cf']) as $line) {
                $htmlLines[] = $line;
                $pdfLines[] = $line;
            }
        } elseif ($values['cf'] !== '') {
            $htmlLines[] = $values['cf'];
            $pdfLines[] = $values['cf'];
        }

        if ($return->prettyPhone !== '') {
            $htmlLines[] = $return->prettyPhone;
            $pdfLines[] = $return->prettyPhone;
        }

        foreach (self::addressLines($values['street'], $values['number'], $values['cap'], $values['city'], $values['province']) as $line) {
            $htmlLines[] = $line;
            $pdfLines[] = $line;
        }

        if ($values['more'] !== '') {
            $htmlLines[] = $values['more'];
            $pdfLines[] = $values['more'];
        }

        if ($htmlLines === []) {
            $return->line = '--';
            $return->pretty = '--';
            $return->prettyPDF = '--';

            return $return;
        }

        $return->pretty = implode('<br>', $htmlLines);
        $return->prettyPDF = implode("\n", $pdfLines);

        return $return;
    }

    /**
     * @return array{
     *   street: string,
     *   number: string,
     *   cap: string,
     *   city: string,
     *   province: string,
     *   country: string,
     *   more: string,
     *   name: string,
     *   surname: string,
     *   phone_prefix: string,
     *   phone: string,
     *   type: string,
     *   business_name: string,
     *   cf: string,
     *   pi: string
     * }
     */
    private static function extractValues(array $row, ?string $prefix = null): array
    {
        return [
            'street' => self::rowValue($row, 'street', $prefix),
            'number' => self::rowValue($row, 'number', $prefix),
            'cap' => self::rowValue($row, 'cap', $prefix),
            'city' => self::rowValue($row, 'city', $prefix),
            'province' => self::rowValue($row, 'province', $prefix),
            'country' => self::rowValue($row, 'country', $prefix),
            'more' => self::rowValue($row, 'more', $prefix),
            'name' => self::rowValue($row, 'name', $prefix),
            'surname' => self::rowValue($row, 'surname', $prefix),
            'phone_prefix' => self::rowValue($row, 'phone_prefix', $prefix),
            'phone' => self::rowValue($row, 'phone', $prefix),
            'type' => self::rowValue($row, 'type', $prefix),
            'business_name' => self::rowValue($row, 'business_name', $prefix),
            'cf' => self::rowValue($row, 'cf', $prefix),
            'pi' => self::rowValue($row, 'pi', $prefix),
        ];
    }

    private static function rowValue(array $row, string $key, ?string $prefix = null): string
    {
        $prefix = self::normalizePrefix($prefix);
        $prefixedKey = $prefix !== '' ? $prefix.'_'.$key : $key;
        $value = $row[$prefixedKey] ?? $row[$key] ?? '';

        return trim((string) $value);
    }

    private static function normalizePrefix(?string $prefix): string
    {
        $prefix = strtolower(trim((string) $prefix));
        $prefix = preg_replace('/[^a-z0-9_]+/', '_', $prefix) ?? '';

        return trim($prefix, '_');
    }

    private static function fullPhone(string $phonePrefix, string $phone): string
    {
        return trim($phonePrefix.$phone);
    }

    private static function prettyPhone(string $phonePrefix, string $phone): string
    {
        $fullPhone = self::fullPhone($phonePrefix, $phone);

        if ($fullPhone === '') {
            return '';
        }

        try {
            return Phone::prettify($fullPhone);
        } catch (\Throwable) {
            return $fullPhone;
        }
    }

    private static function addressLine(
        string $street,
        string $number,
        string $cap,
        string $city,
        string $province
    ): string {
        if ($street === '' || $city === '' || $province === '') {
            return '--';
        }

        $line = $street;

        if ($number !== '') {
            $line .= ' '.$number;
        }

        $locality = trim($cap.' '.$city);

        return $locality !== ''
            ? $line.', '.$locality.' ('.$province.')'
            : $line.', '.$city.' ('.$province.')';
    }

    /**
     * @return list<string>
     */
    private static function addressLines(
        string $street,
        string $number,
        string $cap,
        string $city,
        string $province
    ): array {
        if ($street !== '' && $city !== '' && $province !== '') {
            $streetLine = $street;

            if ($number !== '') {
                $streetLine .= ' '.$number;
            }

            if ($cap !== '') {
                return [$streetLine.', '.$cap, $city.' ('.$province.')'];
            }

            return [$streetLine, $city.' ('.$province.')'];
        }

        if ($street !== '' && $city !== '') {
            return [$street.', '.$city];
        }

        return array_values(array_filter([$street, $city], static fn (string $value): bool => $value !== ''));
    }

    /**
     * @return list<string>
     */
    private static function businessFiscalLines(string $pi, string $cf): array
    {
        $lines = [];

        if ($pi !== '') {
            $lines[] = 'P.Iva '.$pi;
        }

        if ($cf !== '' && $cf !== $pi) {
            $lines[] = 'C.F. '.$cf;
        }

        return $lines;
    }
}
