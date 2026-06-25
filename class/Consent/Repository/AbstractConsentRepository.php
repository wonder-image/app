<?php

    namespace Wonder\Consent\Repository;

    use mysqli;
    use Wonder\Sql\Query;

    abstract class AbstractConsentRepository
    {
        protected mysqli $mysqli;
        protected Query $query;

        public function __construct(?mysqli $mysqli = null)
        {

            $this->query = new Query($mysqli);
            $this->mysqli = $this->query->mysqli;

        }

        protected function now(): string
        {

            return date('Y-m-d H:i:s');

        }

        protected function escape(string $value): string
        {

            return $this->mysqli->real_escape_string($value);

        }

        protected function toSqlValue(mixed $value): string
        {

            if ($value === null) {
                return 'NULL';
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return "'".$this->escape((string) $value)."'";

        }

        /**
         * @return array<int, array<string, mixed>>
         */
        protected function fetchAll(string $sql): array
        {

            $result = $this->mysqli->query($sql);

            if ($result === false) {
                return [];
            }

            return $result->fetch_all(MYSQLI_ASSOC);

        }

        /**
         * @return array<string, mixed>|null
         */
        protected function fetchOne(string $sql): ?array
        {

            $result = $this->mysqli->query($sql);

            if ($result === false || $result->num_rows === 0) {
                return null;
            }

            $row = $result->fetch_assoc();

            return is_array($row) ? $row : null;

        }
    }

