<?php

namespace Dynart\Micro;

use Dynart\Micro\Database\PdoBuilder;

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

    /** @var PdoBuilder */
    protected $pdoBuilder;

    abstract protected function connect(): void;
    abstract public function escapeName(string $name): string;
    abstract public function namedPlaceholderRegex(string $name): string;

    public function __construct(Config $config, Logger $logger, Database\PdoBuilder $pdoBuilder) {
        $this->config = $config;
        $this->logger = $logger;
        $this->pdoBuilder = $pdoBuilder;
    }

    public function connected(): bool {
        return $this->connected;
    }

    protected function setConnected(bool $value): void {
        $this->connected = $value;
    }

    public function query(string $query, array $params = [], bool $closeCursor = false) {
        try {
            $this->connect();
            list($query, $params) = $this->replaceNamedPlaceholders($query, $params);
            $stmt = $this->pdo->prepare($query);
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

    /**
     * @param string $query
     * @param array $params
     * @return array
     */
    protected function replaceNamedPlaceholders(string $query, array $params): array {
        foreach ($params as $n => $v) {
            if ($n[0] == '!') {
                $query = preg_replace($this->namedPlaceholderRegex($n), $v, $query);
                unset($params[$n]);
            }
        }
        return array($query, $params);
    }

    protected function getParametersString($params): string {
        return $params ? "\nParameters: " . ($params ? json_encode($params) : '') : "";
    }

    public function configValue(string $name) {
        return $this->config->get("database.{$this->configName}.$name", "db_{$name}_missing");
    }

    public function fetch($query, $params = [], string $className = '') {
        $stmt = $this->query($query, $params);
        $this->setFetchMode($stmt, $className);
        $result = $stmt->fetch();
        $stmt->closeCursor();
        return $result;
    }

    public function fetchAll(string $query, array $params = [], string $className = '') {
        $stmt = $this->query($query, $params);
        $this->setFetchMode($stmt, $className);
        $result = $stmt->fetchAll();
        $stmt->closeCursor();
        return $result;
    }

    protected function setFetchMode(\PDOStatement $stmt, string $className) {
        if ($className) {
            $stmt->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $className);
        } else {
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        }
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

    public function runInTransaction($callable) {
        $this->beginTransaction();
        try {
            call_user_func($callable); // here the CREATE/DROP table can COMMIT implicitly
            $this->commit(); // here it drops an exception because of that
        } catch (\RuntimeException $e) {
            // ignore "There is no active transaction"
            if ($e->getMessage() == "There is no active transaction") {
                return;
            }
            $this->rollBack();
            throw $e;
        }
    }
}