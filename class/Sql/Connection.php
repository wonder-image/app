<?php

    namespace Wonder\Sql;

    use Wonder\App\Credentials;
    
    use mysqli;
    use Exception;

    class Connection {

        private static $hostname, $port, $username, $password, $database, $charset, $collation;

        private static $connection = [];

        
        public function __construct( $hostname = null, $username = null, $password = null, $database = null ) 
        {

            self::$hostname = ($hostname === null) ? Credentials::database()->hostname : $hostname;
            self::$username = ($username === null) ? Credentials::database()->username : $username;
            self::$password = ($password === null) ? Credentials::database()->password : $password;
            self::$database = ($database === null) ? Credentials::database()->database['main'] : $database;
            self::$charset = trim((string) (Credentials::database()->charset ?? 'latin1'));
            self::$collation = trim((string) (Credentials::database()->collation ?? 'latin1_swedish_ci'));

            [self::$hostname, self::$port] = self::parseHostAndPort((string) self::$hostname);

            if (self::$charset === '') {
                self::$charset = 'latin1';
            }

            if (self::$collation === '') {
                self::$collation = 'latin1_swedish_ci';
            }

        }

        private static function ensureConfigured(): void
        {

            if (
                self::$hostname !== null &&
                self::$username !== null &&
                self::$password !== null &&
                self::$database !== null
            ) {
                return;
            }

            new self();

        }

        public static function Connect( ?string $database = null): mysqli 
        { 

            self::ensureConfigured();

            $database = ($database === null) ? self::$database : Credentials::database()->database[$database];

            $connectionCode = base64_encode(self::$hostname.' | '.self::$username.' | '.self::$password.' | '.$database);

            if (isset(self::$connection[$connectionCode])) {

                return self::$connection[$connectionCode];

            } else {

                $mysqli = new mysqli(
                    self::$hostname,
                    self::$username,
                    self::$password,
                    $database,
                    self::$port
                );
                        
                if ($mysqli->connect_error) {
                    
                    $message = "\r\n";
                    $message .= "Connessione al database ".self::$database." fallita \r\n";
                    $message .= "\r\n";
                    $message .= "Error N°$mysqli->connect_errno\r\n";
                    $message .= "$mysqli->connect_error\r\n";
        
                    throw new Exception($message);

                } else {

                    $charset = is_string(self::$charset) && self::$charset !== '' ? self::$charset : 'latin1';
                    $collation = is_string(self::$collation) ? trim(self::$collation) : '';

                    if (!$mysqli->set_charset($charset)) {

                        $message = "\r\n";
                        $message .= "Impossibile impostare charset MySQL '{$charset}'\r\n";
                        $message .= "Error N°{$mysqli->errno}\r\n";
                        $message .= "{$mysqli->error}\r\n";

                        throw new Exception($message);

                    }

                    if ($collation !== '') {

                        $escapedCollation = $mysqli->real_escape_string($collation);
                        if (!$mysqli->query("SET collation_connection = '{$escapedCollation}'")) {

                            $message = "\r\n";
                            $message .= "Impossibile impostare collation MySQL '{$collation}'\r\n";
                            $message .= "Error N°{$mysqli->errno}\r\n";
                            $message .= "{$mysqli->error}\r\n";

                            throw new Exception($message);

                        }

                    }

                }

                self::logConnection($database);

                self::$connection[$connectionCode] = $mysqli;

                return $mysqli;

            }

        }

        private static function shouldLogConnection(): bool
        {

            return filter_var($_ENV['DB_CONNECTION_LOG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        }

        private static function parseHostAndPort(string $hostname): array
        {

            $hostname = trim($hostname);

            if ($hostname === '') {
                return ['', 3306];
            }

            if (str_contains($hostname, ':') && preg_match('/^(.+):([0-9]+)$/', $hostname, $matches) === 1) {
                return [
                    trim((string) ($matches[1] ?? '')),
                    (int) ($matches[2] ?? 3306),
                ];
            }

            return [$hostname, 3306];

        }

        private static function logConnection( string $database ): void
        {

            if (!self::shouldLogConnection()) {
                return;
            }

            $host = self::$hostname ?? '-';
            $user = self::$username ?? '-';
            $uri = $_SERVER['REQUEST_URI'] ?? '-';
            $pid = function_exists('getmypid') ? getmypid() : '-';

            $line = "[".date('Y-m-d H:i:s')."] [wonder-db] connect host=$host user=$user db=$database pid=$pid uri=$uri";

            $logFile = rtrim(ROOT, "/")."/db-connections.log";
            error_log($line."\n", 3, $logFile);
        

        }

    }
