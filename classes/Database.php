<?php

require_once __DIR__ . '/../config/database.php';

class Database
{
    private static ?Database $instance = null;
    private mysqli $connection;

    private function __construct()
    {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

        if ($this->connection->connect_error) {
            throw new RuntimeException('Database connection failed: ' . $this->connection->connect_error);
        }

        $this->connection->set_charset(DB_CHARSET);
        $this->connection->query("SET time_zone = '+00:00'");
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        // Reconnect if connection lost
        if (!$this->connection->ping()) {
            $this->connection->close();
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
            $this->connection->set_charset(DB_CHARSET);
        }
        return $this->connection;
    }

    public function prepare(string $sql): mysqli_stmt|false
    {
        return $this->getConnection()->prepare($sql);
    }

    public function query(string $sql): mysqli_result|bool
    {
        return $this->getConnection()->query($sql);
    }

    public function escape(string $value): string
    {
        return $this->getConnection()->real_escape_string($value);
    }

    public function lastInsertId(): int
    {
        return (int)$this->getConnection()->insert_id;
    }

    public function affectedRows(): int
    {
        return $this->getConnection()->affected_rows;
    }

    // Prevent cloning
    private function __clone() {}

    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }
}
