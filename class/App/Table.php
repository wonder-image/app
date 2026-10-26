<?php

    namespace Wonder\App;

    class Table {

        public string $name;
        public static array $list = [];

        /** Schemi registrati ma non ancora calcolati: nome => callable. */
        private static array $resolvers = [];

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

            $name = strtolower($name);

            return array_key_exists($name, self::$list) || isset(self::$resolvers[$name]);

        }

        public function schema()
        {

            if (isset(self::$resolvers[$this->name])) {

                // Tolto prima della chiamata: un resolver che rilegge sé stesso non va in loop.
                $resolver = self::$resolvers[$this->name];
                unset(self::$resolvers[$this->name]);

                try {
                    self::$list[$this->name] = (array) $resolver();
                } catch (\Throwable $error) {
                    self::$resolvers[$this->name] = $resolver;
                    throw $error;
                }

            }

            return self::$list[$this->name] ?? [];

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
