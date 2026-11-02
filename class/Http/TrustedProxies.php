<?php

    namespace Wonder\Http;

    use Wonder\App\Credentials;

    /**
     * Quando contano gli header `X-Forwarded-*`.
     *
     * Con `APP_TRUSTED_PROXIES` (IP e CIDR, IPv4 e IPv6, separati da virgole)
     * contano solo se `REMOTE_ADDR` è nella lista, e la lista basta da sola.
     * Senza lista vale `APP_TRUST_PROXY` come prima: fiducia a chiunque si
     * colleghi. Le voci non valide della lista sono ignorate.
     */
    final class TrustedProxies
    {
        public const ENV = 'APP_TRUSTED_PROXIES';

        /** @return list<array{0: string, 1: int}> indirizzo binario e prefisso */
        public static function list(): array
        {
            if (class_exists(Credentials::class)) {
                Credentials::loadEnv();
            }

            $value = $_ENV[self::ENV] ?? $_SERVER[self::ENV] ?? getenv(self::ENV);

            return self::parse(is_string($value) ? $value : '');
        }

        /** @return list<array{0: string, 1: int}> */
        public static function parse(string $value): array
        {
            $ranges = [];

            foreach (explode(',', $value) as $entry) {
                $range = self::range(trim($entry));
                if ($range !== null) {
                    $ranges[] = $range;
                }
            }

            return $ranges;
        }

        /** Gli header del proxy contano per questa richiesta? */
        public static function trusts(?array $server = null): bool
        {
            $list = self::list();

            if ($list === []) {
                return Cookie::trustsProxy();
            }

            $server = $server ?? $_SERVER;

            return self::contains(trim((string) ($server['REMOTE_ADDR'] ?? '')), $list);
        }

        /** @param list<array{0: string, 1: int}> $list */
        public static function contains(string $ip, array $list): bool
        {
            $address = self::address($ip);
            if ($address === null) {
                return false;
            }

            foreach ($list as [$network, $prefix]) {
                if (strlen($network) === strlen($address) && self::matches($address, $network, $prefix)) {
                    return true;
                }
            }

            return false;
        }

        /** @return array{0: string, 1: int}|null */
        private static function range(string $entry): ?array
        {
            if ($entry === '') {
                return null;
            }

            [$ip, $prefix] = array_pad(explode('/', $entry, 2), 2, null);
            $address = self::address($ip);
            if ($address === null) {
                return null;
            }

            $bits = strlen($address) * 8;
            if ($prefix === null) {
                return [$address, $bits];
            }

            if (!ctype_digit($prefix) || (int) $prefix > $bits) {
                return null;
            }

            return [$address, (int) $prefix];
        }

        // IPv4 mappato in IPv6 (`::ffff:1.2.3.4`) vale come IPv4.
        private static function address(string $ip): ?string
        {
            if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $match) === 1) {
                $ip = $match[1];
            }

            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                return null;
            }

            $packed = inet_pton($ip);

            return $packed === false ? null : $packed;
        }

        private static function matches(string $address, string $network, int $prefix): bool
        {
            $bytes = intdiv($prefix, 8);
            if (strncmp($address, $network, $bytes) !== 0) {
                return false;
            }

            $rest = $prefix % 8;
            if ($rest === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $rest)) & 0xFF;

            return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
        }
    }
