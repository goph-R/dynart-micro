<?php

namespace Dynart\Micro;

abstract class Repository {

    /** @var App */
    protected $app;
    /** @var Database */
    protected $db;
    protected $table;
    protected $sqlParams = [];
    protected $orderByFields = [];

    public function __construct(App $app) {
        $this->app = $app;
        $this->db = $app->database();
    }

    public function findById(int $id) {
        $sql = 'select * from '.$this->db->escapeName($this->table).' where id = :id limit 1';
        return $this->db->fetch($sql, [':id' => $id]);
    }

    public function findAll(array $fields, array $params) {
        $sql = $this->getSelect($fields, $params);
        $sql .= $this->getWhere($params);
        $sql .= $this->getOrder($params);
        $sql .= $this->getLimit($params);
        return $this->db->fetchAll($sql, $this->sqlParams);
    }

    public function findAllCount(array $params) {
        $fields = ['c' => ['count(1)']];
        $sql = $this->getSelect($fields, $params);
        $sql .= $this->getWhere($params);
        return $this->db->fetchColumn($sql, $this->sqlParams);
    }

    public function deleteById(int $id) {
        $sql = "delete from ".$this->db->escapeName($this->table)." where id = :id limit 1";
        $this->db->query($sql, [':id' => $id]);
    }

    public function deleteByIds(array $ids) {
        list($condition, $params) = $this->db->getInConditionAndParams($ids);
        $sql = "delete from ".$this->db->escapeName($this->table)." where id in ($condition)";
        $this->db->query($sql, $params);
    }

    protected function getSelect(array $fields, array $params) {
        $select = [];
        $this->orderByFields = [];
        foreach ($fields as $as => $name) {
            $escapedName = is_array($name) ? $name[0] : $this->db->escapeName($name);
            if (is_int($as)) {
                $this->orderByFields[] = $name;
                $select[] = $escapedName;
            } else {
                $this->orderByFields[] = $as;
                $select[] = $escapedName.' as '.$this->db->escapeName($as);
            }
        }
        $table = $this->db->escapeName($this->table);
        $sql = 'select '.join(', ', $select).' from '.$table;
        $sql .= $this->getJoins($fields, $params);
        return $sql;
    }

    protected function getJoins(array $fields, array $params) {
        return '';
    }

    protected function getWhere(array $params) {
        return '';
    }

    protected function getOrder(array $params) {
        if (!isset($params['order_by']) || !isset($params['order_dir'])) {
            return '';
        }
        $orderBy = $params['order_by'];
        if (!in_array($orderBy, $this->orderByFields)) {
            return '';
        }
        $orderDir = $params['order_dir'] == 'desc' ? 'desc' : 'asc';
        return ' order by '.$this->db->escapeName($orderBy).' '.$orderDir;
    }

    protected function getLimit(array $params) {
        if (!isset($params['page']) || !isset($params['page_size'])) {
            return '';
        }
        $page = (int)$params['page'];
        $pageSize = (int)$params['page_size'];
        if ($page < 0) $page = 0;
        if ($pageSize < 1) $pageSize = 1;
        if ($pageSize > 100) $pageSize = 100;
        return ' limit '.($page * $pageSize).', '.$pageSize;
    }    

}