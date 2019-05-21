<?php

namespace ActiveORM;

use PDO;

/**
 * Class Builder.
 */
class Query implements \IteratorAggregate
{
    /**
     * @var array
     */
    private $select;
    /**
     * @var array
     */
    private $insert;
    /**
     * @var array
     */
    private $update;
    /**
     * @var array
     */
    private $delete;
    /**
     * @var array
     */
    private $values;
    /**
     * @var array
     */
    private $set;
    /**
     * @var array
     */
    private $from;
    /**
     * @var array
     */
    private $where;// = [];
    /**
     * @var array
     */
    private $joins;
    /**
     * @var string
     */
    protected $entity;
    /**
     * @var string
     */
    private $group;
    /**
     * @var array
     */
    private $order;
    /**
     * @var int
     */
    private $limit;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * Gloabal PDO that can be set statically, so all Queries use it
     * @var \PDO
     */
    protected static $global_pdo;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * Query constructor.
     *
     * @param null|\PDO $pdo
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public static function setPdo(PDO $pdo)
    {
        self::$global_pdo = $pdo;
    }

    private function getPdo()
    {
        return (
            $this->pdo !== null ?
            $this->pdo :
            self::$global_pdo
        );
    }

    /**
     * @param string $table
     * @param string $alias
     *
     * @return \Goldoni\Builder\Query
     */
    public function from(string $table, ?string $alias = null): self
    {
        if ($alias) {
            $this->from[$table] = $alias;
        } else {
            $this->from[] = $table;
        }

        return $this;
    }

    /**
     * @param mixed $fields
     * @param bool $override Whether to override the existing select fields.
     *  Defaults to false, which appends new select field to the previously selected 
     * @return \Goldoni\Builder\Query
     */
    public function select($fields, $override = false): self
    {
        if(is_string($fields)){
            $fields = explode(',', $fields);
        }

        if($override === true){
            $this->select = $fields;
        } else {
            $this->select = array_merge($this->select, $fields);
        }

        return $this;
    }

    /**
     * @param string $table
     * @param array  $attributes
     *
     * @return \Goldoni\Builder\Query
     */
    public function insert(string $table, ?array $attributes = null): self
    {
        $this->insert = $table;

        if ($attributes) {
            $this->values = $attributes;
        }

        return $this;
    }

    public function value(array $attributes): self
    {
        $this->values = $attributes;

        return $this;
    }

    /**
     * @param string $table
     * @param array  $attributes
     * @param int    $id
     *
     * @return \Goldoni\Builder\Query
     */
    public function update(string $table, ?array $attributes = null, ?int $id = null): self
    {
        $this->update = $table;

        if ($id) {
            $this->where('id = :id');
            $this->params(['id' => $id]);
        }

        if ($attributes) {
            $this->set = $attributes;
        }

        return $this;
    }

    public function set(array $attributes): self
    {
        $this->set = $attributes;

        return $this;
    }

    public function delete(string $table, ?int $id = null): self
    {
        $this->delete = $table;

        if ($id) {
            $this->where('id = :id');
            $this->params(['id' => $id]);
        }

        return $this;
    }

    /**
     * @param string ...$conditions
     *
     * @return \Goldoni\Builder\Query
     */
    public function where($conditions, $operator = 'AND'): self
    {
        if($conditions !== null){
            if($this->where == null){
                $this->where = new ConditionBlock($conditions, $operator);
            } else {
                $this->where->append($conditions);
            }
        }

        return $this;
    }

    /**
     * @param string $table
     * @param string $condition
     * @param string $type
     *
     * @return \Goldoni\Builder\Query
     */
    public function join(string $table, string $condition, string  $type = 'inner'): self
    {
        $this->joins[$type][] = [$table, $condition];

        return $this;
    }

    public function leftJoin(string $table, string $condition, string  $type = 'left'): self
    {
        $this->joins[$type][] = [$table, $condition];

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @return int
     */
    public function count(): int
    {
        $query = clone $this;
        $table = current($this->from);

        return $query->select("COUNT({$table}.id)")->execute()->fetchColumn();
    }

    /**
     * @param string      $column
     * @param null|string $direction
     *
     * @return \Goldoni\Builder\Query
     */
    public function orderBy(string $column, ?string $direction = 'ASC'): self
    {
        $this->order[$column] = $direction;

        return $this;
    }

    /**
     * @param string $column
     *
     * @return \Goldoni\Builder\Query
     */
    public function groupBy(string $column): self
    {
        $this->group = $column;

        return $this;
    }

    /**
     * @param int $limit
     * @param int $offset
     *
     * @return \Goldoni\Builder\Query
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit = "$offset, $limit";

        return $this;
    }

    /**
     * @param string $entity
     *
     * @return \Goldoni\Builder\Query
     */
    public function into(string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function fetchOne()
    {
        $record = $this->execute()->fetch(\PDO::FETCH_ASSOC);

        if (false === $record) {
            return false;
        }

        if ($this->entity) {
            $className = $this->entity;
            $model = new $className();
            $model->hydrate($record);
            $this->afterModelHydrated($model);
            $this->afterAllFetchedAndHydrated([ $model ]);
            return $model;
        }

        return (object) $record;
    }

    public function fetchAll()
    {
        $rows = $this->execute()->fetchAll(\PDO::FETCH_ASSOC);

        if (false === $rows) {
            return false;
        }

        $result = new \Ds\Vector();
        if ($this->entity) {
            foreach($rows as $row){
                $className = $this->entity;
                $model = new $className();
                $model->hydrate($row);
                $this->afterModelHydrated($model);
                $result->push($model);
            }
            $this->afterAllFetchedAndHydrated($result);
            return $result;
        }

        return (object) $rows;
    }

    protected function afterModelHydrated($model)
    {

    }

    protected function afterAllFetchedAndHydrated($result)
    {

    }

    /**
     * @throws \Exception
     */
    public function fetchOrFail()
    {
        $record = $this->fetchOne();

        if (false === $record) {
            throw new \Exception('No query results for model');
        }

        return $record;
    }

    /**
     * @param array $params
     * @param bool  $merge
     *
     * @return \Goldoni\Builder\Query
     */
    public function params(array $params, bool $merge = true): self
    {
        if ($merge) {
            $this->params = array_merge($this->params, $params);
        } else {
            $this->params = $params;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $parts = ['SELECT'];

        if ($this->select) {
            $parts[] = implode(', ', $this->select);
        } else {
            $parts[] = '*';
        }

        if ($this->insert) {
            $parts = ['INSERT INTO ' . $this->insert];
        }

        if ($this->values) {
            $parts[] = '(' . implode(', ', array_keys($this->values)) . ')';
            $parts[] = 'VALUES';
            $parts[] = '(' . implode(', ', array_values($this->values)) . ')';
        }

        if ($this->update) {
            $parts = ['UPDATE ' . $this->update . ' SET'];
        }

        if ($this->set) {
            $sets = [];

            foreach ($this->set as $key => $value) {
                $sets[] = "$key = $value";
            }
            $parts[] = implode(', ', $sets);
        }

        if ($this->delete) {
            $parts = ['DELETE FROM ' . $this->delete];
        }

        if ($this->from) {
            $parts[] = 'FROM';
            $parts[] = $this->buildFrom();
        }

        if (!empty($this->joins)) {
            foreach ($this->joins as $type => $joins) {
                foreach ($joins as [$table, $condition]) {
                    $parts[] = mb_strtoupper($type) . " JOIN $table ON $condition";
                }
            }
        }

        //if (!empty($this->where)) {
        if ($this->where != null) {
            $parts[] = 'WHERE';
            $parts[] = (string) $this->where; //'(' . implode(') AND (', $this->where) . ')';
        }

        if ($this->order) {
            foreach ($this->order as $key => $value) {
                $parts[] = "ORDER BY $key $value";
            }
        }

        if ($this->group) {
            $parts[] = 'GROUP BY ' . $this->group;
        }

        if ($this->limit) {
            $parts[] = 'LIMIT ' . $this->limit;
        }

        return implode(' ', $parts);
    }

    private function buildFrom(): string
    {
        $from = [];

        foreach ($this->from as $key => $value) {
            if (\is_string($key)) {
                $from[] = "$key as $value";
            } else {
                $from[] = $value;
            }
        }

        return implode(', ', $from);
    }

    public function execute()
    {
        $sql = $this->__toString();
        echo $sql."\n";

        if (!empty($this->params)) {
            $statement = $this->getPdo()->prepare($sql);

            if (!$statement->execute($this->params)) {
                throw new \Exception("Sql Error by execute query: {$sql}");
            }

            return $statement;
        }

        return $this->getPdo()->query($sql);
    }

    /**
     * @return \Goldoni\Builder\QueryResult|\Traversable
     */
    public function getIterator()
    {
        return $this->fetchAll();
    }
}
