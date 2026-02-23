<?php

    namespace Wonder\App;

    use mysqli;
    use Wonder\Sql\{ Connection, Query, CreateTable };

    abstract class Model {

        public static $table, $folder;

        protected static ?string $dbHostname, $dbUsername, $dbPassword, $dbName;

        protected static ?string $defaultCondition = "";

        public static Connection $connection;

        abstract public static function tableSchema(): array;
        abstract public static function dataSchema(): array;

        public static function query(): Query
        { 

            return new Query(self::connection());

        }

        public static function setConnection()
        {

            $connection = new Connection( 
                    self::$dbHostname ?? null, 
                    self::$dbUsername ?? null, 
                    self::$dbPassword ?? null, 
                    self::$dbName ?? null
                );
            
            self::$connection = $connection;

        }

        public static function connection(): mysqli 
        {

            if (empty(self::$connection)) { self::setConnection(); }
            
            return self::$connection->Connect();

        }

        public static function getColumns(): array 
        {

            $columns = [];
            foreach (static::tableSchema() as $key => $column) { $columns[$column->name] = $column->schema; }

            return $columns;

        }

        public static function createTable() 
        {

            $database = new CreateTable(self::connection());
            $database->Table(static::$table, self::getColumns());

        }

        # Prepare

            public static function arrayValues( array $values, $prefix = '' ): array
            {

                $schema = static::dataSchema();
                $columns = self::getColumns();

                $valuesArray = [];

                foreach ($schema as $class) {

                    if (array_key_exists($class->key, $columns)) {
                        
                        $key = "{$prefix}{$class->key}";

                        if ($class->isRequired()) {

                            // Se obbligatorio, preparalo anche se è null (farà scattare un errore interno)
                            $value = $values[$key] ?? null;

                            $valuesArray[$key] = [
                                'class' => $class,
                                'value' => $value
                            ];

                        } else {

                            // Se non è richiesto, lo prepariamo solo se è stato passato
                            if (isset($values[$key])) {

                                $valuesArray[$key] = [
                                    'class' => $class,
                                    'value' => $values[$key]
                                ];

                            }

                        }
                        
                    } else {

                        throw new \Exception(
                            "La colonna ".$class->key." non fa parte delle colonne del Database."
                        );

                    }

                }

                return $valuesArray;

            }

            public static function validate( array $values, $prefix = '' ): object 
            {
                
                $validatedValues = (object) [];

                $validatedValues->valid = true;
                $validatedValues->response = [];

                foreach (self::arrayValues($values, $prefix) as $key => $value) {

                    $class = $value['class'];
                    $validatedValues->response[$key] = $class->validate($value['value'], $values);

                    if (!$validatedValues->response[$key]->isValid()) {
                        $validatedValues->valid = false;
                    }

                }

                return $validatedValues;

            }

            public static function prepare( array $values, $prefix = ''): array 
            {

                $preparedValues = [];

                foreach (self::arrayValues($values, $prefix) as $key => $value) {
                    $preparedValues[str_replace($prefix, '', $key)] = $value['class']->format($value['value']);
                }

                return $preparedValues;

            }

        # Get

            public static function getAll( string | array $column = '*' ): array
            {

                return self::query()->Select(
                    static::$table,
                    null,
                    null,
                    null,
                    null,
                    $column
                )->row;

            }

            public static function get( $condition = null, $limit = null, $order = null, $orderDirection = null, $column = '*' ): object 
            {

                return self::query()->Select(
                    static::$table,
                    $condition,
                    $limit,
                    $order,
                    $orderDirection,
                    $column
                )->row;

            }

            public static function getById($id): object 
            {

                return self::query()->Select(
                    static::$table, 
                    [ 'id' => $id ], 
                    1
                )->row;

            }

        # Operation

            public static function create($values)
            {

                $validated = self::validate($values);
                
                if (!$validated->valid) {

                    return (object) array_merge( [ 'success' => false], (array) $validated );

                }

                return self::query()->Insert(
                    static::$table,
                    self::prepare($values)
                );

            }

            public static function update( array $values, int $id ): object 
            {

                $validated = self::validate($values);
                
                if (!$validated->valid) {

                    return (object) array_merge( [ 'success' => false], (array) $validated );

                }

                return self::query()->Update(
                    static::$table,
                    self::prepare($values),
                    'id',
                    $id
                );

            }

            public static function delete()
            {


            }

            public static function createUpdate($values, $id = null)
            {

                if (empty($id)) {
                    return self::create($values);
                } else {
                    return self::update($values, $id);
                }

            }
            
    }
