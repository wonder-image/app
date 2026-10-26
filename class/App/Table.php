<?php

    namespace Wonder\App;

    class Table {

        public string $name;

        /**
         * Registro degli schemi: nome => schema. Una chiave lazy vale `[]`
         * finché non viene risolta.
         *
         * @internal La lettura diretta non risolve le chiavi lazy: per
         * l'esistenza usa `has()`, per il contenuto `key(...)->schema()`.
         */
        public static array $list = [];

        /** Schemi registrati ma non ancora calcolati: nome => callable. */
        private static array $resolvers = [];

        /** Schemi in calcolo: nome => true se nella catena c'è stato un rientro. */
        private static array $resolving = [];

        public function __construct( string $name )
        {

            $this->name = strtolower($name);

        }

        public static function key( string $tableName ): static
        {

            return new self($tableName);

        }

        public function setSchema( array $schema ): static 
        {

            self::$list[$this->name] = $schema;
            unset(self::$resolvers[$this->name]);
            return $this;

        }

        /**
         * Schema calcolato al primo accesso (schema(), prepare(), ...) e poi
         * memorizzato in `$list`. Fino ad allora `$list` ha la chiave con `[]`,
         * così i controlli `array_key_exists` esistenti la riconoscono.
         *
         * Il resolver gira una volta, nel contesto del primo chiamante (es.
         * dentro il Repeater con `NAME` della tabella figlia). Se rilegge la
         * propria chiave, anche passando da un altro schema (A → B → A),
         * `schema()` lancia una `LogicException` e non memorizza nulla.
         */
        public function setSchemaResolver( callable $resolver ): static
        {

            self::$list[$this->name] = [];
            self::$resolvers[$this->name] = $resolver;
            return $this;

        }

        /** Registrata (anche lazy), senza calcolare lo schema. */
        public static function has( string $name ): bool
        {

            return array_key_exists(strtolower($name), self::$list);

        }

        public function schema()
        {

            $name = $this->name;

            if (array_key_exists($name, self::$resolving)) {
                // Nessuno schema della catena va memorizzato, anche se un resolver intercetta l'eccezione.
                self::$resolving = array_fill_keys(array_keys(self::$resolving), true);
                throw self::reentryError($name);
            }

            // Il resolver vale solo se la chiave è ancora in $list (es. non dopo `$list = [...]`).
            if (isset(self::$resolvers[$name]) && array_key_exists($name, self::$list)) {

                $resolver = self::$resolvers[$name];
                self::$resolving[$name] = false;

                try {
                    $schema = (array) $resolver();
                    // Rientro intercettato dal resolver (es. safeFormFieldsByKey()): schema incompleto.
                    $reentered = self::$resolving[$name];
                } finally {
                    unset(self::$resolving[$name]);
                }

                if ($reentered) {
                    throw self::reentryError($name);
                }

                self::$list[$name] = $schema;
                unset(self::$resolvers[$name]);

            }

            return self::$list[$name] ?? [];

        }

        private static function reentryError( string $name ): \LogicException
        {

            $chain = array_keys(self::$resolving);
            $chain[] = $name;

            return new \LogicException("Table: lo schema '{$name}' viene riletto dal proprio resolver (".implode(' → ', $chain)."): il resolver non può leggere la propria chiave.");

        }

        public function columns(): array 
        {

            $columns = array_keys($this->schema());

            array_push($columns, 'id');
            array_push($columns, 'last_modified');
            array_push($columns, 'creation');
            array_push($columns, 'deleted');

            return array_values($columns);

        }

        public function hasColumn( string $columnName ): bool
        {

            return in_array(trim($columnName), $this->columns(), true);

        }

        public function prepare( array $post, ?array $oldValues = null ): array
        {

            return formToArray($this->name, $post, $this->schema(), $oldValues);

        }

        public function prepareFor( string $tableName, array $post, ?array $oldValues = null ): array
        {

            return formToArray($tableName, $post, $this->schema(), $oldValues);

        }

    }
