<?php

abstract class Repository {
    protected $db;
    protected $table;
    protected $modelClass;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->setTableName();
        $this->setModelClass();
    }

    abstract protected function setTableName();
    abstract protected function setModelClass();

    /**
     * Create a model instance from an associative array of data
     * @param array $data Associative array representing model data
     * @return object Returns an instance of the model class
     */
    protected function createModel($data) {
        $modelClass = $this->modelClass;
        return new $modelClass($data);
    }

    /**
     * Create a collection of model instances from an array of data
     * @param array $dataArray Array of associative arrays representing model data
     * @return array Returns an array of model instances
     */
    protected function createModelCollection($dataArray) {
        $models = [];
        foreach ($dataArray as $data) {
            $models[] = $this->createModel($data);
        }
        return $models;
    }

    /**
     * Find by its ID
     * @param int $id
     * @return mixed|null Returns the model instance or null if not found
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $data = $this->db->fetchOne($sql, [$id]);
        
        return $data ? $this->createModel($data) : null;
    }

    /**
     * Find all
     * @param string $orderBy Column to order by
     * @param string $direction Direction of ordering (ASC or DESC)
     * @return array Returns an array of model instances
     */
    public function findAll($orderBy = 'id', $direction = 'ASC') {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction}";
        $dataArray = $this->db->fetchAll($sql);
        
        return $this->createModelCollection($dataArray);
    }

    /**
     * Find by criteria of a where clause
     * example: findBy(['status' => 'active', 'id' => [1, 2, 3]])
     * @param array $criteria Associative array of field => value pairs
     * @param string|null $orderBy Column to order by
     * @param int|null $limit Limit the number of results
     * @return array Returns an array of model instances
     */
    public function findBy($criteria, $orderBy = null, $limit = null) {
        $whereClause = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $whereClause[] = "{$field} IN ({$placeholders})";
                $params = array_merge($params, $value);
            } else {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($whereClause)) {
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $dataArray = $this->db->fetchAll($sql, $params);
        return $this->createModelCollection($dataArray);
    }

    /**
     * Find one by criteria
     * example: findOneBy(['status' => 'active', 'id' => 1])
     * @param array $criteria Associative array of field => value pairs
     * @return mixed|null Returns a single model instance or null if not found
     */
    public function findOneBy($criteria) {
        $result = $this->findBy($criteria, null, 1);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Count records based on criteria
     * example: count(['status' => 'active', 'id' => [1, 2, 3]])
     * @param array $criteria Associative array of field => value pairs
     * @return int Returns the count of records matching the criteria
     */
    public function count($criteria = []) {
        $whereClause = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $whereClause[] = "{$field} IN ({$placeholders})";
                $params = array_merge($params, $value);
            } else {
                $whereClause[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        
        if (!empty($whereClause)) {
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        $result = $this->db->fetchOne($sql, $params);
        return (int)$result['count'];
    }

}