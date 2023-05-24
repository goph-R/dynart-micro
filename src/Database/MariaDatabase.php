<?php

namespace Dynart\Micro\Database;

use Dynart\Micro\Database;

class MariaDatabase extends Database {

    protected function connect(): void {
        if ($this->connected()) {
            return;
        }
        $this->pdo = $this->pdoBuilder
            ->dsn($this->configValue('dsn'))
            ->username($this->configValue('username'))
            ->password($this->configValue('password'))
            ->options([\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION])
            ->build();
        $this->setConnected(true);
        $dbName = $this->escapeName($this->configValue('name'));
        $this->query("use $dbName");
        $this->query("set names 'utf8'");
    }

    public function escapeName(string $name): string {
        $parts = explode('.', $name);
        return '`'.join('`.`', $parts).'`';
    }

    public function namedPlaceholderRegex(string $name): string {
        return '/(?<!["\'])\\'.$name.'/';
    }

}