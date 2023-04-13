<?php

namespace Dynart\Micro\Database;

use Dynart\Micro\Database;

class MariaDatabase extends Database {

    protected function connect() {
        if ($this->connected) {
            return;
        }
        $dsn = $this->config->get('database.'.$this->name.'.dsn', 'mysql:localhost');
        $user = $this->config->get('database.'.$this->name.'.username', 'root');
        $password = $this->config->get('database.'.$this->name.'.password', '');
        $this->pdo = $this->pdoBuilder
            ->dsn($dsn)
            ->username($user)
            ->password($password)
            ->options([\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION])
            ->build();
        $this->connected = true;
        $this->query("use ".$this->config->get('database.'.$this->name.'.name', 'db_name_missing'));
        $this->query("set names 'utf8'");
    }

    public function escapeName(string $name) {
        $parts = explode('.', $name);
        return '`'.join('`.`', $parts).'`';
    }
}