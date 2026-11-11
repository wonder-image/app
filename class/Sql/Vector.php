<?php

    namespace Wonder\Sql;

    use InvalidArgumentException;
    use mysqli;

    /**
     * Valore di una colonna VECTOR di MariaDB (11.7+): embedding per la
     * ricerca per similarità. In scrittura `Query` lo emette come
     * `VEC_FromText('[...]')`; in lettura la colonna arriva in binario e si
     * rilegge con `fromBinary()`.
     */
    final class Vector
    {

        public const MAX_DIMENSIONS = 16383;

        public const MIN_SERVER = '11.7.0';

        /** Metrica => funzione SQL di distanza. */
        public const DISTANCES = [
            'cosine' => 'VEC_DISTANCE_COSINE',
            'euclidean' => 'VEC_DISTANCE_EUCLIDEAN',
        ];

        /** @var list<float> */
        private array $values;

        /** @param array<int|float|string> $values */
        public function __construct( array $values )
        {

            if ($values === [] || count($values) > self::MAX_DIMENSIONS) {
                throw new InvalidArgumentException('Vector: servono da 1 a '.self::MAX_DIMENSIONS.' dimensioni.');
            }

            $clean = [];

            foreach ($values as $value) {

                if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
                    throw new InvalidArgumentException('Vector: ammessi solo numeri.');
                }

                $value = (float) $value;

                if (!is_finite($value)) {
                    throw new InvalidArgumentException('Vector: numero non finito.');
                }

                $clean[] = $value;

            }

            $this->values = $clean;

        }

        /** @param self|array<int|float|string> $vector */
        public static function from( self | array $vector ): self
        {

            return $vector instanceof self ? $vector : new self($vector);

        }

        /** Valore letto da una colonna VECTOR: float a 32 bit little-endian. */
        public static function fromBinary( string $binary ): self
        {

            if ($binary === '' || strlen($binary) % 4 !== 0) {
                throw new InvalidArgumentException('Vector: valore binario non valido.');
            }

            return new self(array_values(unpack('g*', $binary)));

        }

        /** @return list<float> */
        public function toArray(): array
        {

            return $this->values;

        }

        public function dimensions(): int
        {

            return count($this->values);

        }

        /** `[0.1,0.2,...]`: solo cifre, segno, punto ed esponente. */
        public function toText(): string
        {

            return '['.implode(',', array_map(
                static fn (float $value): string => rtrim(rtrim(sprintf('%.9F', $value), '0'), '.'),
                $this->values
            )).']';

        }

        /** Espressione SQL del valore, sicura senza escape della connessione. */
        public function toSql(): string
        {

            return "VEC_FromText('".$this->toText()."')";

        }

        /**
         * Espressione di distanza tra una colonna e un vettore, per
         * `$attributes` e `$order` di `Query::Select()`. `$column` è un
         * identificatore scritto dal codice (`colonna` o `tabella.colonna`).
         * L'indice vettoriale viene usato solo con `ORDER BY <distanza> LIMIT n`
         * e con la stessa metrica dichiarata nell'indice.
         *
         * @param self|array<int|float|string> $vector
         */
        public static function distance( string $column, self | array $vector, string $metric = 'cosine' ): string
        {

            $function = self::DISTANCES[self::metric($metric)];

            $identifier = implode('.', array_map(
                [Query::class, 'escapeIdentifier'],
                explode('.', $column)
            ));

            return $function.'('.$identifier.', '.self::from($vector)->toSql().')';

        }

        /** Metrica normalizzata; una sconosciuta lancia InvalidArgumentException. */
        public static function metric( string $metric ): string
        {

            $metric = strtolower(trim($metric));

            if (!isset(self::DISTANCES[$metric])) {
                throw new InvalidArgumentException('Vector: metrica non valida, ammesse '.implode(' e ', array_keys(self::DISTANCES)).'.');
            }

            return $metric;

        }

        public static function supported( mysqli $connection ): bool
        {

            return self::serverSupports((string) $connection->server_info);

        }

        /** `11.8.9-MariaDB-...` sì; MySQL e MariaDB precedenti alla 11.7 no. */
        public static function serverSupports( string $serverInfo ): bool
        {

            if (preg_match('/(\d+\.\d+\.\d+)-MariaDB/i', $serverInfo, $match) !== 1) {
                return false;
            }

            return version_compare($match[1], self::MIN_SERVER, '>=');

        }

    }
