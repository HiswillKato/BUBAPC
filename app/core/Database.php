<?php
declare(strict_types= 1);
namespace Martazi\core;
use mysqli;

final class Database{
    private mysqli $connection;

    public function __construct(array $config){
        $this->connection = new mysqli(
            hostname: $config['host'], 
            username: $config['user'], 
            password: $config['password'],
            database: $config['name'],
            port: (int) $config['port']
        );
        $this->connection->set_charset($config['charset']);

    }

    public function connection(): mysqli{
    return $this->connection;
    }

    public function close(): void{
    $this->connection->close();
    }

}