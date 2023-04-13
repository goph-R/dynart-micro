<?php

namespace Dynart\Micro;

class Database {

    protected $name = 'default';
    protected $connected = false;

    /** @var \PDO */
    protected $pdo;

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var PdoBuilder */
    protected $pdoBuilder;

    public function __construct(Config $config, Logger $logger, PdoBuilder $pdoBuilder) {
        $this->config = $config;
        $this->pdoBuilder = $pdoBuilder;
    }

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

    public function query(string $query, array $params=[]) {
        $this->connect();
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
        } catch (\RuntimeException $e) {
            $this->logger->error("SQL error:\n$query\nParameters: ".($params ? json_encode($params) : ''));
            throw $e;
        }
        return $stmt;
    }

    public function fetch($query, $params=[]) {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $result;
    }

    public function fetchAll(string $query, array $params=[]) {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $result;
    }

    public function fetchColumn(string $query, array $params=[], int $index = 0) {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetchColumn($index);
        $stmt = null;
        return $result;
    }

    public function lastInsertId($name=null) {
        return $this->pdo->lastInsertId($name);
    }

    public function insert(string $tableName, array $data) {
        $tableName = $this->escapeName($tableName);
        $params = [];
        $names = [];
        foreach ($data as $name => $value) {
            $names[] = $this->escapeName($name);
            $params[':'.$name] = $value;
        }
        $namesString = join(', ', $names);
        $paramsString = join(', ', array_keys($params));
        $sql = "insert into $tableName ($namesString) values ($paramsString)";
        $this->query($sql, $params);
    }

    public function update(string $tableName, array $data, string $condition='', array $conditionParams=[]) {
        $tableName = $this->escapeName($tableName);
        $params = [];
        $pairs = [];
        foreach ($data as $name => $value) {
            $pairs[] = $this->escapeName($name).' = :'.$name;
            $params[':'.$name] = $value;
        }
        $params = array_merge($params, $conditionParams);
        $pairsString = join(', ', $pairs);
        $where = $condition ? ' where '.$condition : '';
        $sql = "update $tableName set $pairsString$where";
        $this->query($sql, $params);
    }

    public function getInConditionAndParams(array $values, $paramNamePrefix='in') {
        $params = [];
        $in = "";
        foreach ($values as $i => $item) {
            $key = ":".$paramNamePrefix.$i;
            $in .= "$key,";
            $params[$key] = $item;
        }
        $condition = rtrim($in, ",");
        return [$condition, $params];
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }

}