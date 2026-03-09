<?php

    namespace Wonder\App;

    class Table {

        public string $name;
        public static array $list = [];

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
            return $this;

        }

        public function schema() {

            return self::$list[$this->name];

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

    }