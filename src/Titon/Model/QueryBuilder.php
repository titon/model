<?php
namespace Titon\Model;

use Titon\Db\Query;
use Titon\Model\Exception\InvalidRelationQueryException;
use Titon\Model\ModelAware;
use \Closure;

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
        $query = $relatedModel->select();

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

        $relation->fetch($query);

        return $this;
    }

}