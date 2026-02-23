<?php

    namespace Wonder\App;

    use Wonder\App\{ Path };

    use Wonder\Sql\{ Query };
    use Wonder\Backend\Table\Table as Datatable;

    use mysqli;

    abstract class Resource {

        public static $model;

        public static array | string $condition = [ 'deleted' => 'false' ];
        public static $limit = null;
        public static string $orderColumn = 'creation';
        public static string $orderDirection = 'DESC';


        abstract public static function labelSchema(): array;
        abstract public static function textSchema(): array;
        abstract public static function formSchema(): array;
        abstract public static function pageSchema(): array;

        private static function query(): Query
        {

            return static::$model::query();

        }

        private static function connection(): mysqli
        {

            return static::$model::connection();

        }

        public static function getInput($key): object
        {
            
            $filtered = array_filter(static::formSchema(), fn($item) => $item->name === $key);

            return reset($filtered);

        }

        public static function getLabel($key = null): string | array
        {
            
            return $key == null ? static::labelSchema() : static::labelSchema()[$key] ?? '';

        }

        public static function getText($key): string | array
        {
            
            return $key == null ? static::textSchema() : static::textSchema()[$key] ?? '';

        }

    }
