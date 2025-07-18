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

        public function prepare( array $post, ?array $oldValues = null ): array
        {

            return formToArray($this->name, $post, self::$list[$this->name], $oldValues);

        }

    }