<?php

    namespace Wonder\Sql;

    use Wonder\App\Credentials;

    use ArrayAccess;

    class ConnectionPool implements ArrayAccess {

        private $hostname;
        private $username;
        private $password;
        private $database;
        private $databases = [];
        private $connections = [];

        public function __construct( $hostname = null, $username = null, $password = null, ?array $database = null ) 
        {

            $this->hostname = ($hostname === null) ? Credentials::database()->hostname : $hostname;
            $this->username = ($username === null) ? Credentials::database()->username : $username;
            $this->password = ($password === null) ? Credentials::database()->password : $password;
            $this->database = ($database === null) ? Credentials::database()->database : $database;

            foreach ($this->database as $alias => $database) {
                $this->databases[$alias] = $database;
                $this->databases[$database] = $database;
            }

        }

        public function offsetExists(mixed $offset): bool
        {

            return isset($this->databases[$offset]) || isset($this->connections[$offset]);

        }

        public function offsetGet(mixed $offset): mixed
        {

            if (isset($this->connections[$offset])) {
                return $this->connections[$offset];
            }

            if (!isset($this->databases[$offset])) {
                return null;
            }

            $database = $this->databases[$offset];

            if (isset($this->connections[$database])) {
                $this->connections[$offset] = $this->connections[$database];
                return $this->connections[$offset];
            }

            $connection = new Connection( $this->hostname, $this->username, $this->password, $database );
            $mysqli = $connection->Connect();

            $this->connections[$database] = $mysqli;
            $this->connections[$offset] = $mysqli;

            return $mysqli;

        }

        public function offsetSet(mixed $offset, mixed $value): void
        {

            if ($offset === null) {
                $this->connections[] = $value;
            } else {
                $this->connections[$offset] = $value;
            }

        }

        public function offsetUnset(mixed $offset): void
        {

            unset($this->connections[$offset]);

        }

    }
