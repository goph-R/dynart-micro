<?php

namespace Dynart\Micro;

abstract class Database
{

    protected $configName = 'default';
    protected $connected = false;

    /** @var \PDO */
    protected $pdo;

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var Database\PdoBuilder */
    protected $pdoBuilder;

    protected $varNames = [];
    protected $varValues = [];

    abstract protected function connect(): void;
    abstract public function escapeName(string $name): string;

    public function __construct(Config $config, Logger $logger, Database\PdoBuilder $pdoBuilder) {
        $this->config = $config;
        $this->logger = $logger;
        $this->pdoBuilder = $pdoBuilder;
    }

    public function connected(): bool {
        return $this->connected;
    }

    public function addConfigVar(string $name) {
        $this->varNames[$name] = '/*'.$name.'*/';
        $this->varValues[$name] = $this->config->get('database.'.$this->configName.'.'.$name);
    }

    protected function setConnected(bool $value): void {
        $this->connected = $value;
    }

    public function query(string $query, array $params = [], bool $closeCursor = false) {
        try {
            $this->connect();
            $stmt = $this->pdo->prepare($this->replaceConfigVars($query));
            $stmt->execute($params);
            if ($this->logger->level() == Logger::DEBUG) { // because of the json_encode
                $this->logger->debug("Query: $query" . $this->getParametersString($params));
            }
        } catch (\PDOException $e) {
            $this->logger->error("Error in query: $query" . $this->getParametersString($params));
            throw $e;
        }
        if ($closeCursor) {
            $stmt->closeCursor();
        }
        return $stmt;
    }

    protected function getParametersString($params): string {
        return $params ? "\nParameters: " . ($params ? json_encode($params) : '') : "";
    }

    public function configValue(string $name) {
        return $this->config->get("database.{$this->configName}.$name", "db_{$name}_missing");
    }

    public function fetch($query, $params = []) {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $result;
    }

    public function fetchAll(string $query, array $params = []) {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $result;
    }

    public function fetchColumn(string $query, array $params = []) {
        /** @var \PDOStatement $stmt */
        $stmt = $this->query($query, $params);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stmt->closeCursor();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $row;
        }
        return $result;
    }

    public function fetchOne(string $query, array $params = []) {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetchColumn(0);
        $stmt = null;
        return $result;
    }

    public function lastInsertId($name = null) {
        return $this->pdo->lastInsertId($name);
    }

    public function insert(string $tableName, array $data) {
        $tableName = $this->escapeName($tableName);
        $params = [];
        $names = [];
        foreach ($data as $name => $value) {
            $names[] = $this->escapeName($name);
            $params[':' . $name] = $value;
        }
        $namesString = join(', ', $names);
        $paramsString = join(', ', array_keys($params));
        $sql = "insert into $tableName ($namesString) values ($paramsString)";
        $this->query($sql, $params, true);
    }

    public function update(string $tableName, array $data, string $condition = '', array $conditionParams = []) {
        $tableName = $this->escapeName($tableName);
        $params = [];
        $pairs = [];
        foreach ($data as $name => $value) {
            $pairs[] = $this->escapeName($name) . ' = :' . $name;
            $params[':' . $name] = $value;
        }
        $params = array_merge($params, $conditionParams);
        $pairsString = join(', ', $pairs);
        $where = $condition ? ' where ' . $condition : '';
        $sql = "update $tableName set $pairsString$where";
        $this->query($sql, $params, true);
    }

    public function getInConditionAndParams(array $values, $paramNamePrefix = 'in') {
        $params = [];
        $in = "";
        foreach ($values as $i => $item) {
            $key = ":" . $paramNamePrefix . $i;
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

    public function replaceConfigVars(string $sql) {
        return str_replace($this->varNames, $this->varValues, $sql);
    }

}