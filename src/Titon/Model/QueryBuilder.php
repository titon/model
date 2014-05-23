<?php

namespace Titon\Db;


class QueryBuilder {

    /**
     * Include a repository relation by querying and joining the records.
     *
     * @param string|string[] $alias
     * @param \Titon\Db\Query|\Closure $query
     * @return $this
     * @throws \Titon\Db\Exception\InvalidRelationQueryException
     */
    public function with($alias, $query = null) {
        if ($this->getType() !== static::SELECT) {
            throw new InvalidRelationQueryException('Only select queries can join related data');
        }

        // Allow an array of aliases to easily be set
        if (is_array($alias)) {
            foreach ($alias as $name) {
                $this->with($name);
            }

            return $this;
        }

        $relation = $this->getRepository()->getRelation($alias); // Do relation check

        if ($query instanceof Query) {
            $relatedQuery = $query;

            if ($query->getType() !== static::SELECT) {
                throw new InvalidRelationQueryException('Only select sub-queries are permitted for related data');
            }
        } else {
            $relatedQuery = $relation->getRelatedRepository()->select();

            // Apply relation conditions
            if ($conditions = $relation->getConditions()) {
                $relatedQuery->bindCallback($conditions, $relation);
            }

            // Apply with conditions
            if ($query instanceof Closure) {
                $relatedQuery->bindCallback($query, $relation);
            }
        }

        // Add foreign key to field list
        if ($this->_fields) {
            if ($relation->getType() === Relation::MANY_TO_ONE) {
                $this->fields([$relation->getForeignKey()], true);
            }
        }

        $this->_relationQueries[$alias] = $relatedQuery;

        return $this;
    }

    /**
     * Primary method that handles the processing of delete queries.
     * Will wrap all delete queries in a transaction call.
     * Will delete related data if $cascade is true.
     * Triggers callbacks before and after.
     *
     * @param \Titon\Db\Query $query
     * @param int|int[] $id
     * @param mixed $options {
     *      @type bool $cascade         Will delete related dependent records
     *      @type bool $preCallback     Will trigger before callbacks
     *      @type bool $postCallback    Will trigger after callbacks
     * }
     * @return int The count of records deleted
     * @throws \Titon\Db\Exception\QueryFailureException
     * @throws \Exception
     */
    /*protected function _processDelete(Query $query, $id, $options = []) {
        if (is_bool($options)) {
            $options = ['cascade' => $options];
        }

        $options = $options + [
            'cascade' => true,
            'preCallback' => true,
            'postCallback' => true
        ];

        // If a falsey value is returned, exit early
        // If an integer is returned, return it
        if ($options['preCallback']) {
            $event = $this->emit('db.preDelete', [$id, &$options['cascade']]);
            $state = $event->getData();

            if ($state !== null) {
                if (!$state) {
                    return 0;
                } else if (is_numeric($state)) {
                    return $state;
                }
            }
        }

        // Update the connection group
        $driver = $this->getDriver();
        $driver->setContext('delete');

        // Use transactions for cascading
        if ($options['cascade']) {
            if (!$driver->startTransaction()) {
                throw new QueryFailureException('Failed to start database transaction');
            }

            try {
                $count = $query->save();

                if ($count === false) {
                    throw new QueryFailureException(sprintf('Failed to delete %s record with ID %s', get_class($this), implode(', ', (array) $id)));
                }

                //$this->deleteDependents($id, $options['cascade']);

                $driver->commitTransaction();

            // Rollback and re-throw exception
            } catch (Exception $e) {
                $driver->rollbackTransaction();

                throw $e;
            }

        // No transaction needed for single query
        } else {
            $count = $query->save();

            if ($count === false) {
                return 0;
            }
        }

        $this->data = [];

        if ($options['postCallback']) {
            $this->emit('db.postDelete', [$id]);
        }

        return $count;
    }*/

    /**
     * Primary method that handles the processing of update queries.
     * Will wrap all delete queries in a transaction call.
     * If any related data exists, update those records after verifying required IDs.
     * Validate schema data and related data structure before updating.
     *
     * @param \Titon\Db\Query $query
     * @param int|int[] $id
     * @param array $data
     * @param mixed $options {
     *      @type bool $preCallback     Will trigger before callbacks
     *      @type bool $postCallback    Will trigger after callbacks
     * }
     * @return int The count of records updated
     * @throws \Titon\Db\Exception\QueryFailureException
     * @throws \Exception
     */
    /*protected function _processSave(Query $query, $id, array $data, $options = []) {
        $isCreate = !$id;
        $options = $options + [
            'preCallback' => true,
            'postCallback' => true
        ];

        if ($options['preCallback']) {
            $event = $this->emit('db.preSave', [$id, &$data]);
            $state = $event->getData();

            if ($state !== null && !$state) {
                return 0;
            }
        }

        // Filter and set the data
        if ($columns = $this->getSchema()->getColumns()) {
            $data = array_intersect_key($data, $columns);
        }

        $query->fields($data);

        // Update the connection context
        $driver = $this->getDriver();
        $driver->setContext('write');

        // Update the records using transactions
        if (isset($relatedData)) {
            if (!$driver->startTransaction()) {
                throw new QueryFailureException('Failed to start database transaction');
            }

            try {
                $count = $query->save();

                if ($count === false) {
                    throw new QueryFailureException(sprintf('Failed to update %s record with ID %s', get_class($this), $id));
                }

                if ($isCreate) {
                    $id = $driver->getLastInsertID($this);
                }

                //$this->upsertRelations($id, $relatedData, $options);

                $driver->commitTransaction();

            // Rollback and re-throw exception
            } catch (Exception $e) {
                $driver->rollbackTransaction();

                throw $e;
            }

        // No transaction needed for single query
        } else {
            $count = $query->save();

            if ($count === false) {
                return 0;
            }

            if ($isCreate) {
                $id = $driver->getLastInsertID($this);
            }
        }

        if (!is_array($id)) {
            $this->id = $id;
            $this->setData([$this->getPrimaryKey() => $id] + $data);
        }

        if ($options['postCallback']) {
            $this->emit('db.postSave', [$id, $isCreate]);
        }

        if ($isCreate) {
            return $id;
        }

        return $count;
    }*/

}