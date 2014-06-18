<?php
namespace Titon\Model;

use Titon\Db\Query;
use Titon\Model\Exception\InvalidRelationQueryException;
use Titon\Model\ModelAware;
use \Closure;

/**
 * Class QueryBuilder
 *
 * @package Titon\Model
 * @method \Titon\Model\ModelCollection all(array $options = [])
 * @method \Titon\Model\QueryBuilder attribute(string $key, mixed $value)
 * @method int avg(string $field)
 * @method \Titon\Model\QueryBuilder bindCallback(callable $callback, mixed $argument = null)
 * @method \Titon\Model\QueryBuilder cache(string $key, mixed $expires = null)
 * @method int count()
 * @method \Titon\Model\QueryBuilder data(mixed $data)
 * @method \Titon\Model\QueryBuilder distinct()
 * @method \Titon\Model\QueryBuilder except($query, string $flag)
 * @method \Titon\Model\QueryBuilder fields(array $fields, bool $merge = false)
 * @method \Titon\Model\ModelCollection|\Titon\Model\Model|array find(string $type, array $options = [])
 * @method \Titon\Model\Model first(array $options = [])
 * @method \Titon\Model\QueryBuilder from(string $table, string $alias = '')
 * @method \Titon\Model\QueryBuilder groupBy()
 * @method \Titon\Model\QueryBuilder having(string $field, string $op, mixed $value = '')
 * @method \Titon\Model\QueryBuilder innerJoin(string $table, array $fields, array $on)
 * @method \Titon\Model\QueryBuilder intersect($query, string $flag)
 * @method \Titon\Model\QueryBuilder leftJoin(string $table, array $fields, array $on)
 * @method \Titon\Model\QueryBuilder limit(int $limit, int $offset = 0)
 * @method array lists(string $value = '', string $key = '', array $options = [])
 * @method int max(string $field)
 * @method int min(string $field)
 * @method \Titon\Model\QueryBuilder offset(int $offset)
 * @method \Titon\Model\QueryBuilder orderBy(string $field, string $direction)
 * @method \Titon\Model\QueryBuilder orHaving(string $field, string $op, mixed $value = '')
 * @method \Titon\Model\QueryBuilder orWhere(string $field, string $op, mixed $value = '')
 * @method \Titon\Model\QueryBuilder outerJoin(string $table, array $fields, array $on)
 * @method \Titon\Model\QueryBuilder rightJoin(string $table, array $fields, array $on)
 * @method int save(array $data = [])
 * @method \Titon\Model\QueryBuilder schema($schema)
 * @method \Titon\Model\QueryBuilder straightJoin(string $table, array $fields, array $on)
 * @method \Titon\Db\Query\SubQuery subQuery()
 * @method int sum(string $field)
 * @method string toString()
 * @method \Titon\Model\QueryBuilder union($query, string $flag)
 * @method \Titon\Model\QueryBuilder where(string $field, string $op, mixed $value = '')
 * @method \Titon\Model\QueryBuilder xorHaving(string $field, string $op, mixed $value = '')
 * @method \Titon\Model\QueryBuilder xorWhere(string $field, string $op, mixed $value = '')
 */
class QueryBuilder {
    use ModelAware;

    protected $_query;

    public function __construct(Query $query, Model $model) {
        $this->_query = $query;
        $this->setModel($model);
    }

    public function __call($method, array $arguments) {
        $result = call_user_func_array([$this->getQuery(), $method], $arguments);

        if ($result instanceof Query) {
            return $this;
        }

        return $result;
    }

    public function getQuery() {
        return $this->_query;
    }

    public function with($alias, Closure $conditions = null) {
        if ($this->getQuery()->getType() !== Query::SELECT) {
            throw new InvalidRelationQueryException('Only select queries can join related data');
        }

        // Allow an array of aliases to easily be set
        if (is_array($alias)) {
            foreach ($alias as $name => $conditions) {
                if (is_string($conditions)) {
                    $name = $conditions;
                    $conditions = null;
                }

                $this->with($name, $conditions);
            }

            return $this;
        }

        $model = $this->getModel();
        $relation = $model->getRelation($alias);
        $relatedModel = $relation->getRelatedModel();

        // Create a new query
        $query = $relatedModel->query(Query::SELECT);

        // Apply relation conditions
        if ($baseConditions = $relation->getConditions()) {
            $query->bindCallback($baseConditions, $relation);
        }

        // Apply custom conditions
        if ($conditions) {
            $query->bindCallback($conditions, $relation);
        }

        // Add foreign key to field list
        if ($this->getQuery()->getFields()) {
            if ($relation->getType() === Relation::MANY_TO_ONE) {
                $this->getQuery()->fields([$relation->getPrimaryForeignKey()], true);
            }
        }

        $relation->eagerLoad($query);

        return $this;
    }

}