<?php

    namespace Wonder\Plugin\Custom\Tax;

    class Check {

        static $patterns = [
            // IT - Codice Fiscale / Partita IVA
            'IT' => [
                'vat' => '([0-9]{11})',
                'tin' => [
                    'private' => '([A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z])',
                    'business' => '([0-9]{11}|[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z])',
                ],
            ],
            // DE - Steuer-ID / Steuernummer
            'DE' => [
                'vat' => '([0-9]{9})',
                'tin' => [
                    'private' => '([0-9]{11})',
                    'business' => '([0-9]{11})',
                ],
            ],
            // FR - Numero fiscale de reference
            'FR' => [
                'vat' => '([A-Z0-9]{2}[0-9]{9})',
                'tin' => [
                    'private' => '([0-9]{13})',
                    'business' => '([0-9]{13})',
                ],
            ],
            // ES - NIF
            'ES' => [
                'vat' => '([A-Z0-9][0-9]{7}[A-Z0-9])',
                'tin' => [
                    'private' => '([A-Z0-9][0-9]{7}[A-Z0-9])',
                    'business' => '([A-Z0-9][0-9]{7}[A-Z0-9])',
                ],
            ],
            // NL - BSN / RSIN
            'NL' => [
                'vat' => '([0-9]{9}B[0-9]{2})',
                'tin' => [
                    'private' => '([0-9]{9})',
                    'business' => '([0-9]{9})',
                ],
            ],
            // BE - Numero nazionale / Numero impresa
            'BE' => [
                'vat' => '(0?[0-9]{9})',
                'tin' => [
                    'private' => '([0-9]{11})',
                    'business' => '([0-9]{11})',
                ],
            ],
            // AT - Steuernummer
            'AT' => [
                'vat' => '(U[0-9]{8})',
                'tin' => [
                    'private' => '([0-9]{9})',
                    'business' => '([0-9]{9})',
                ],
            ],
            // IE - PPSN / Tax Reference Number
            'IE' => [
                'vat' => '([0-9A-Z]{7,8})',
                'tin' => [
                    'private' => '([0-9A-Z]{7,8})',
                    'business' => '([0-9A-Z]{7,8})',
                ],
            ],
            // PL - PESEL / NIP
            'PL' => [
                'vat' => '([0-9]{10})',
                'tin' => [
                    'private' => '([0-9]{11})',
                    'business' => '([0-9]{11})',
                ],
            ],
            // Default - TIN estero (generico)
            'default' => [
                'vat' => '([A-Z0-9]{5,15})',
                'tin' => [
                    'private' => '([A-Z0-9]{5,20})',
                    'business' => '([A-Z0-9]{5,20})',
                ],
            ],
        ];

        public static function vat(string $vat, ?string $country = null): object
        {
            
            $RETURN = (object) [];
            $RETURN->valid = false;
            $RETURN->country = ($country != null) ? strtoupper(trim($country)) : null;
            $RETURN->number = null;
            $RETURN->vat = null;

            // Pulizia
            $raw = strtoupper(trim($vat));
            $raw = preg_replace('/[^A-Z0-9]/', '', $raw);

            $patterns = self::$patterns;

            if (empty($raw)) {
                return $RETURN;
            }

            // 1) Country forzato
            if ($RETURN->country != null) {

                $cc = $RETURN->country;

                if (!isset($patterns[$cc]) || !isset($patterns[$cc]['vat'])) {
                    return $RETURN;
                }

                // Rimuovo prefisso paese se presente
                if (str_starts_with($raw, $cc)) {
                    $raw = substr($raw, 2);
                }

                if (!preg_match('/^' . $patterns[$cc]['vat'] . '$/', $raw, $m)) {
                    return $RETURN;
                }

                $RETURN->valid = true;
                $RETURN->country = $cc;
                $RETURN->number = $m[1];
                $RETURN->vat = $cc.$m[1];

                return $RETURN;

            }

            // 2) Auto-detect
            foreach ($patterns as $cc => $data) {

                if ($cc == 'default' || !isset($data['vat'])) {
                    continue;
                }

                if (preg_match('/^' . $cc . $data['vat'] . '$/', $raw, $m)) {
                    
                    $RETURN->valid = true;
                    $RETURN->country = $cc;
                    $RETURN->number = $m[1];
                    $RETURN->vat = $cc.$m[1];
                    return $RETURN;

                }

            }

            // 3) Fallback UE
            if (preg_match('/^([A-Z]{2})([A-Z0-9]{5,15})$/', $raw, $m)) {
                
                $cc = $m[1];
                $rx = $patterns['default']['vat'] ?? null;

                if ($rx != null && preg_match('/^' . $rx . '$/', $m[2], $x)) {
                    $RETURN->valid = true;
                    $RETURN->country = $cc;
                    $RETURN->number = $x[0];
                    $RETURN->vat = $cc.$x[0];

                    return $RETURN;
                }

            }

            return $RETURN;
            
        }

        public static function tin(string $tin, string $country, ?string $type = null): object
        {
            
            $RETURN = (object) [];
            $RETURN->valid = false;
            $RETURN->tin = null;
            $RETURN->country = strtoupper(trim($country));

            // Pulizia
            $raw = strtoupper(trim($tin));
            $raw = preg_replace('/[^A-Z0-9]/', '', $raw);

            $type = strtolower(trim((string)$type));
            if (!in_array($type, [ 'business', 'private', 'all' ])) {
                $type = 'all';
            }

            $patterns = self::$patterns;

            if (empty($raw) || empty($RETURN->country)) {
                return $RETURN;
            }

            // Country forzato
            $cc = $RETURN->country;

            $patternSet = $patterns[$cc]['tin'] ?? ($patterns['default']['tin'] ?? null);
            if ($patternSet == null) {
                return $RETURN;
            }

            $pattern = null;
            if (is_array($patternSet)) {
                if ($type == 'all') {
                    $patternBusiness = $patternSet['business'] ?? null;
                    $patternPrivate = $patternSet['private'] ?? null;

                    if ($patternBusiness != null && $patternPrivate != null && $patternBusiness != $patternPrivate) {
                        $pattern = '(?:'.$patternBusiness.'|'.$patternPrivate.')';
                    } else {
                        $pattern = $patternBusiness ?? $patternPrivate;
                    }
                } else {
                    $pattern = $patternSet[$type] ?? null;
                }
            } else {
                $pattern = $patternSet;
            }

            if ($pattern == null) {
                return $RETURN;
            }

            // Rimuovo prefisso paese se presente
            if (str_starts_with($raw, $cc)) {
                $raw = substr($raw, 2);
            }

            if (!preg_match('/^' . $pattern . '$/', $raw, $m)) {
                return $RETURN;
            }

            $RETURN->valid = true;
            $RETURN->country = $cc;
            $RETURN->tin = $m[0];

            return $RETURN;
            
        }

    }
