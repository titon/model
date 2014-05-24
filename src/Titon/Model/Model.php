<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Titon\Common\Traits\Instanceable;
use Titon\Common\Traits\Mutable;
use Titon\Db\Behavior;
use Titon\Db\Entity;
use Titon\Db\Finder;
use Titon\Db\Query;
use Titon\Db\Repository;
use Titon\Db\RepositoryAware;
use Titon\Event\Event;
use Titon\Event\Listener;
use Titon\Event\Traits\Emittable;
use Titon\Model\Exception\InvalidRelationStructureException;
use Titon\Model\Exception\MissingPrimaryKeyException;
use Titon\Model\Exception\MissingRelationException;
use Titon\Model\Relation\ManyToMany;
use Titon\Model\Relation\ManyToOne;
use Titon\Model\Relation\OneToMany;
use Titon\Model\Relation\OneToOne;
use Titon\Utility\Hash;
use Titon\Utility\Inflector;
use Titon\Utility\Validator;
use \Closure;

/**
 * A model is a multi-functional architectural layer that provides access to a database (a repository),
 * represents a single record of data (an entity), provides relational mapping between models,
 * mass data assignment protection and filtering, data validation, and more,
 * all through the popular ActiveRecord pattern.
 *
 * @link http://en.wikipedia.org/wiki/Active_record_pattern
 * @link http://en.wikipedia.org/wiki/Relational_model
 *
 * @package Titon\Model
 */
class Model extends Entity implements Listener {
    use Emittable, Instanceable, RepositoryAware;

    /**
     * Mapping of many-to-one relations.
     *
     * @type array
     */
    protected $belongsTo = [];

    /**
     * Mapping of many-to-many relations.
     *
     * @type array
     */
    protected $belongsToMany = [];

    /**
     * The connection driver key.
     *
     * @type string
     */
    protected $connection = 'default';

    /**
     * The field representing a readable label.
     *
     * @type string[]
     */
    protected $displayField = ['title', 'name', 'id'];

    /**
     * Column names that can automatically be filled and passed into the data layer.
     *
     * @type string[]
     */
    protected $fillable = [];

    /**
     * Column names that are not fillable. If the value is [*], then all columns are guarded.
     *
     * @type string[]
     */
    protected $guarded = [];

    /**
     * Mapping of one-to-one relations.
     *
     * @type array
     */
    protected $hasOne = [];

    /**
     * Mapping of one-to-many relations.
     *
     * @type array
     */
    protected $hasMany = [];

    /**
     * Custom validation error messages.
     *
     * @type string[]
     */
    protected $messages = [];

    /**
     * Prefix to prepend to the table name.
     *
     * @type string
     */
    protected $prefix = '';

    /**
     * The field representing the primary key.
     *
     * @type string
     */
    protected $primaryKey = 'id';

    /**
     * Database table name.
     *
     * @type string
     */
    protected $table = '';

    /**
     * Validation rules.
     *
     * @type array
     */
    protected $validate = [];

    /**
     * List of validation errors.
     *
     * @type string[]
     */
    protected $_errors = [];

    /**
     * Whether the current record exists.
     * Will be set after a find(), save() or delete().
     *
     * @type bool
     */
    protected $_exists = false;

    /**
     * Model to model relationships, the "ORM".
     *
     * @type \Titon\Model\Relation[]
     */
    protected $_relations = [];

    /**
     * Validator instance.
     *
     * @type \Titon\Utility\Validator
     */
    protected $_validator;

    /**
     * Initiate the model and create a new table object based on model settings.
     * Optionally allow row data to be set.
     *
     * @param array $data
     */
    public function __construct(array $data = []) {
        $this->on('model', $this);
        $this->loadRelationships();
        $this->initialize();
        $this->mapData($data);
    }

    /**
     * @see \Titon\Db\Repository::addBehavior()
     */
    public function addBehavior(Behavior $behavior) {
        if (method_exists($behavior, 'setModel')) {
            $behavior->setModel($this);
        }

        return $this->getRepository()->addBehavior($behavior);
    }

    /**
     * @see \Titon\Db\Repository::addFinder()
     */
    public function addFinder($key, Finder $finder) {
        return $this->getRepository()->addFinder($key, $finder);
    }

    /**
     * Add a relation between two models.
     *
     * @param \Titon\Model\Relation $relation
     * @return $this
     */
    public function addRelation(Relation $relation) {
        $relation->setPrimaryModel($this);

        $this->_relations[$relation->getAlias()] = $relation;

        return $this;
    }

    /**
     * Add a many-to-one relationship.
     *
     * @param string $alias
     * @param string $class
     * @param string $foreignKey
     * @param \Closure $conditions
     * @return $this
     */
    public function belongsTo($alias, $class, $foreignKey = null, Closure $conditions = null) {
        $relation = (new ManyToOne($alias, $class))
            ->setPrimaryForeignKey($foreignKey);

        if ($conditions) {
            $relation->setConditions($conditions);
        }

        return $this->addRelation($relation);
    }

    /**
     * Add a many-to-many relationship.
     *
     * @param string $alias
     * @param string $class
     * @param string $junction
     * @param string $foreignKey
     * @param string $relatedKey
     * @param \Closure $conditions
     * @return $this
     */
    public function belongsToMany($alias, $class, $junction, $foreignKey = null, $relatedKey = null, Closure $conditions = null) {
        $relation = (new ManyToMany($alias, $class))
            ->setJunction($junction)
            ->setPrimaryForeignKey($foreignKey)
            ->setRelatedForeignKey($relatedKey);

        if ($conditions) {
            $relation->setConditions($conditions);
        }

        return $this->addRelation($relation);
    }

    /**
     * Delete the record that is currently present in the model instance.
     *
     * @see \Titon\Db\Repository::delete()
     *
     * @param array $options
     * @return int
     * @throws \Titon\Model\Exception\MissingPrimaryKeyException
     */
    public function delete(array $options = []) {
        $id = $this->get($this->primaryKey);

        if (!$id) {
            throw new MissingPrimaryKeyException(sprintf('Cannot delete %s record if no ID is present', get_class($this)));
        }

        if ($count = $this->getRepository()->delete($id, $options)) {
            $this->_data = [];
            $this->_exists = false;

            return $count;
        }

        return 0;
    }

    /**
     * Loop through all table relations and delete dependent records using the ID as a base.
     * Will return a count of how many dependent records were deleted.
     *
     * @param int|int[] $id
     * @param bool $cascade Will delete related records if true
     * @return int The count of records deleted
     * @throws \Titon\Db\Exception\QueryFailureException
     * @throws \Exception
     */
    /*public function deleteDependents($id, $cascade = true) {
        $count = 0;
        $driver = $this->getDriver();

        if (!$driver->startTransaction()) {
            throw new QueryFailureException('Failed to start database transaction');
        }

        try {
            foreach ($this->getRelations() as $relation) {
                if (!$relation->isDependent()) {
                    continue;
                }

                switch ($relation->getType()) {
                    case Relation::ONE_TO_ONE:
                    case Relation::ONE_TO_MANY:
                        $relatedRepo = $relation->getRelatedRepository();
                        $results = [];

                        // Fetch IDs before deletion
                        // Only delete if relations exist
                        if ($cascade && $relatedRepo->hasRelations()) {
                            $results = $relatedRepo
                                ->select($relatedRepo->getPrimaryKey())
                                ->where($relation->getRelatedForeignKey(), $id)
                                ->all();
                        }

                        // Delete all records at once
                        $count += $relatedRepo
                            ->query(Query::DELETE)
                            ->where($relation->getRelatedForeignKey(), $id)
                            ->save();

                        // Loop through the records and cascade delete dependents
                        if ($results) {
                            $dependentIDs = [];

                            foreach ($results as $result) {
                                $dependentIDs[] = $result->get($relatedRepo->getPrimaryKey());
                            }

                            $count += $relatedRepo->deleteDependents($dependentIDs, $cascade);
                        }
                    break;

                    case Relation::MANY_TO_MANY:
                        $junctionRepo = $relation->getJunctionRepository();

                        // Only delete the junction records
                        // The related records should stay
                        $count += $junctionRepo
                            ->query(Query::DELETE)
                            ->where($relation->getForeignKey(), $id)
                            ->save();
                    break;
                }
            }

            $driver->commitTransaction();

        // Rollback and re-throw exception
        } catch (Exception $e) {
            $driver->rollbackTransaction();

            throw $e;
        }

        return $count;
    }*/

    /**
     * Return a boolean on whether the current record exists.
     *
     * @return bool
     */
    public function exists() {
        return $this->_exists;
    }

    /**
     * Fill the model with data to be sent to the database layer.
     *
     * @param array $data
     * @return \Titon\Model\Model
     */
    public function fill(array $data) {
        $this->flush();

        if ($this->isFullyGuarded() || !$data) {
            return $this;
        }

        foreach ($data as $key => $value) {
            if ($this->isFillable($key) && !$this->isGuarded($key)) {
                $this->set($key, $value);
            }
        }

        return $this;
    }

    /**
     * Empty all data in the model.
     *
     * @return \Titon\Model\Model
     */
    public function flush() {
        $this->_data = [];
        $this->_exists = false;
        $this->_errors = [];

        return $this;
    }

    /**
     * Once the primary query has been executed and the results have been fetched,
     * loop over all sub-queries and fetch related data.
     *
     * The related data will be added to the array indexed by the relation alias.
     *
     * @uses Titon\Utility\Hash
     *
     * @param \Titon\Db\Query $query
     * @param Entity $result
     * @param array $options
     * @return \Titon\Db\Entity
     */
    /*public function fetchRelations(Query $query, Entity $result, array $options = []) {
        $queries = $query->getRelationQueries();

        if (!$queries) {
            return $result;
        }

        foreach ($queries as $alias => $subQuery) {
            $newQuery = clone $subQuery;
            $relation = $this->getRelation($alias);
            $relatedRepo = $relation->getRelatedRepository();
            $relatedClass = get_class($relatedRepo);

            switch ($relation->getType()) {

                // Has One
                // The related table should be pointing to this table
                // So use this ID in the related foreign key
                // Since we only want one record, limit it and single fetch
                case Relation::ONE_TO_ONE:
                    $foreignValue = $result[$this->getPrimaryKey()];

                    $newQuery
                        ->where($relation->getRelatedForeignKey(), $foreignValue)
                        ->cache([$relatedClass, 'fetchOneToOne', $foreignValue])
                        ->limit(1);

                    $result->set($alias, function() use ($newQuery, $options) {
                        return $newQuery->first($options);
                    });
                break;

                // Has Many
                // The related tables should be pointing to this table
                // So use this ID in the related foreign key
                // Since we want multiple records, fetch all with no limit
                case Relation::ONE_TO_MANY:
                    $foreignValue = $result[$this->getPrimaryKey()];

                    $newQuery
                        ->where($relation->getRelatedForeignKey(), $foreignValue)
                        ->cache([$relatedClass, 'fetchOneToMany', $foreignValue]);

                    $result->set($alias, function() use ($newQuery, $options) {
                        return $newQuery->all($options);
                    });
                break;

                // Belongs To
                // This table should be pointing to the related table
                // So use the foreign key as the related ID
                // We should only be fetching a single record
                case Relation::MANY_TO_ONE:
                    $foreignValue = $result[$relation->getForeignKey()];

                    $newQuery
                        ->where($relatedRepo->getPrimaryKey(), $foreignValue)
                        ->cache([$relatedClass, 'fetchManyToOne', $foreignValue])
                        ->limit(1);

                    $result->set($alias, function() use ($newQuery, $options) {
                        return $newQuery->first($options);
                    });
                break;

                // Has And Belongs To Many
                // This table points to a related table through a junction table
                // Query the junction table for lookup IDs pointing to the related data
                case Relation::MANY_TO_MANY:
                    $foreignValue = $result[$this->getPrimaryKey()];

                    if (!$foreignValue) {
                        continue;
                    }

                    $result->set($alias, function() use ($relation, $newQuery, $foreignValue, $options) {
                        $relatedRepo = $relation->getRelatedRepository();
                        $relatedClass = get_class($relatedRepo);
                        $lookupIDs = [];

                        // Fetch the related records using the junction IDs
                        $junctionRepo = $relation->getJunctionRepository();
                        $junctionResults = $junctionRepo
                            ->select()
                            ->where($relation->getForeignKey(), $foreignValue)
                            ->cache([get_class($junctionRepo), 'fetchManyToMany', $foreignValue])
                            ->all();

                        if (!$junctionResults) {
                            return [];
                        }

                        foreach ($junctionResults as $result) {
                            $lookupIDs[] = $result->get($relation->getRelatedForeignKey());
                        }

                        $m2mResults = $newQuery
                            ->where($relatedRepo->getPrimaryKey(), $lookupIDs)
                            ->cache([$relatedClass, 'fetchManyToMany', $lookupIDs])
                            ->all($options);

                        // Include the junction data
                        foreach ($m2mResults as $i => $m2mResult) {
                            foreach ($junctionResults as $junctionResult) {
                                if ($junctionResult[$relation->getRelatedForeignKey()] == $m2mResult[$relatedRepo->getPrimaryKey()]) {
                                    $m2mResults[$i]->set('Junction', $junctionResult);
                                }
                            }
                        }

                        return $m2mResults;
                    });
                break;
            }

            // Trigger query immediately
            if (!empty($options['eager'])) {
                $result->get($alias);
            }

            unset($newQuery);
        }

        return $result;
    }*/

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null) {
        $value = parent::get($key, $default);

        if ($method = $this->hasAccessor($key)) {
            return $this->{$method}($value);
        }

        return $value;
    }

    /**
     * Return the validator errors indexed by attribute.
     *
     * @return string[]
     */
    public function getErrors() {
        return $this->_errors;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository() {
        if ($repo = $this->_repository) {
            return $repo;
        }

        $this->setRepository(new Repository([
            'connection' => $this->connection,
            'table' => $this->table,
            'prefix' => $this->prefix,
            'primaryKey' => $this->primaryKey,
            'displayField' => $this->displayField,
            'entity' => get_class($this)
        ]));

        return $this->_repository;
    }

    /**
     * Return a relation by alias.
     *
     * @param string $alias
     * @return \Titon\Model\Relation
     * @throws \Titon\Model\Exception\MissingRelationException
     */
    public function getRelation($alias) {
        if ($this->hasRelation($alias)) {
            return $this->_relations[$alias];
        }

        throw new MissingRelationException(sprintf('Repository relation %s does not exist', $alias));
    }

    /**
     * Return all relations, or all relations by type.
     *
     * @param int $type
     * @return \Titon\Model\Relation[]
     */
    public function getRelations($type = 0) {
        if (!$type) {
            return $this->_relations;
        }

        $relations = [];

        foreach ($this->_relations as $relation) {
            if ($relation->getType() === $type) {
                $relations[$relation->getAlias()] = $relation;
            }
        }

        return $relations;
    }

    /**
     * Return the validator instance.
     *
     * @return \Titon\Utility\Validator
     */
    public function getValidator() {
        if (!$this->_validator) {
            $this->setValidator(Validator::makeFromShorthand([], $this->validate));
        }

        return $this->_validator;
    }

    /**
     * Check to see if an accessor method exists on the current model.
     * If so, return the method name, else return null.
     *
     * @param string $field
     * @return string
     */
    public function hasAccessor($field) {
        $method = sprintf('get%sAttribute', Inflector::camelCase($field));

        if (method_exists($this, $method)) {
            return $method;
        }

        return null;
    }

    /**
     * Check to see if a mutator method exists on the current model.
     * If so, return the method name, else return null.
     *
     * @param string $field
     * @return string
     */
    public function hasMutator($field) {
        $method = sprintf('set%sAttribute', Inflector::camelCase($field));

        if (method_exists($this, $method)) {
            return $method;
        }

        return null;
    }

    /**
     * Add a one-to-one relationship.
     *
     * @param string $alias
     * @param string $class
     * @param string $relatedKey
     * @param bool $dependent
     * @param \Closure $conditions
     * @return $this
     */
    public function hasOne($alias, $class, $relatedKey = null, $dependent = true, Closure $conditions = null) {
        $relation = (new OneToOne($alias, $class))
            ->setRelatedForeignKey($relatedKey)
            ->setDependent($dependent);

        if ($conditions) {
            $relation->setConditions($conditions);
        }

        return $this->addRelation($relation);
    }

    /**
     * Add a one-to-many relationship.
     *
     * @param string $alias
     * @param string $class
     * @param string $relatedKey
     * @param bool $dependent
     * @param \Closure $conditions
     * @return $this
     */
    public function hasMany($alias, $class, $relatedKey = null, $dependent = true, Closure $conditions = null) {
        $relation = (new OneToMany($alias, $class))
            ->setRelatedForeignKey($relatedKey)
            ->setDependent($dependent);

        if ($conditions) {
            $relation->setConditions($conditions);
        }

        return $this->addRelation($relation);
    }

    /**
     * Check if the relation exists.
     *
     * @param string $alias
     * @return bool
     */
    public function hasRelation($alias) {
        return isset($this->_relations[$alias]);
    }

    /**
     * Check if any relation has been set.
     *
     * @return bool
     */
    public function hasRelations() {
        return (count($this->_relations) > 0);
    }

    /**
     * Method that is called immediately after construction.
     */
    public function initialize() {
        return;
    }

    /**
     * Check if a column is fillable. It's fillable if the array is empty, or the column name is in the list.
     *
     * @param string $key
     * @return bool
     */
    public function isFillable($key) {
        return (!$this->fillable || in_array($key, $this->fillable));
    }

    /**
     * Check to see if all columns are guarded.
     *
     * @return bool
     */
    public function isFullyGuarded() {
        return ($this->guarded === ['*']);
    }

    /**
     * Check if a column is guarded. It's guarded if the column is in the list, or if guarded is a * (all columns).
     *
     * @param string $key
     * @return bool
     */
    public function isGuarded($key) {
        return ($this->isFullyGuarded() || in_array($key, $this->guarded));
    }

    /**
     * Load relationships by reflecting current model properties.
     *
     * @return \Titon\Model\Model
     */
    public function loadRelationships() {
        $this->_loadBelongsTo();
        $this->_loadBelongsToMany();
        $this->_loadHasOne();
        $this->_loadHasMany();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function preDelete(Event $event, $id) {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function preFind(Event $event, Query $query, $finder) {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function preSave(Event $event, $id, array &$data, $type) {
        return true;
    }

    /**
     * Method called before validation occurs.
     * Returning a boolean false will cease validation.
     *
     * @param \Titon\Event\Event $event
     * @param \Titon\Model\Model $model
     * @param \Titon\Utility\Validator $validator
     * @return bool
     */
    public function preValidate(Event $event, Model $model, Validator $validator) {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function postDelete(Event $event, $id) {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function postFind(Event $event, array &$results, $finder) {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function postSave(Event $event, $id, $type) {
        return;
    }

    /**
     * Method called after validation occurs.
     *
     * @param \Titon\Event\Event $event
     * @param \Titon\Model\Model $model
     * @param bool $passed
     */
    public function postValidate(Event $event, Model $model, $passed = true) {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function registerEvents() {
        return [
            'db.preSave' => 'preSave',
            'db.postSave' => 'postSave',
            'db.preDelete' => 'preDelete',
            'db.postDelete' => 'postDelete',
            'db.preFind' => 'preFind',
            'db.postFind' => 'postFind',
            'model.preValidate' => 'preValidate',
            'model.postValidate' => 'postValidate'
        ];
    }

    /**
     * Save a record to the database table using the data that has been set to the model.
     * Will return the record ID or 0 on failure.
     *
     * @see \Titon\Db\Repository::upsert()
     *
     * @param array $options
     * @return int
     */
    public function save(array $options = []) {
        $options = $options + ['validate' => true];
        $passed = $options['validate'] ? $this->validate() : true;

        if (!$passed) {
            return 0;
        }

        $data = $this->toArray();
        $id = 0;

        if ($data && ($id = $this->getRepository()->upsert($data, null, $options))) {
            $this->_exists = true;
            $this->set($this->primaryKey, $id);
        } else {
            $this->_exists = false;
        }

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value = null) {
        if ($method = $this->hasMutator($key)) {
            $this->{$method}($value);
        } else {
            parent::set($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRepository(Repository $repository) {
        $repository->on('model', $this);

        $this->_repository = $repository;

        return $this;
    }

    /**
     * Set the validator instance.
     *
     * @param \Titon\Utility\Validator $validator
     * @return \Titon\Model\Model
     */
    public function setValidator(Validator $validator) {
        $this->_validator = $validator;

        return $this;
    }

    /**
     * Either update or insert related data for the primary repository's ID.
     * Each relation will handle upserting differently.
     *
     * @param int $id
     * @param array $data
     * @param array $options
     * @return int
     * @throws \Titon\Db\Exception\QueryFailureException
     * @throws \Exception
     */
    /*public function upsertRelations($id, array $data, array $options = []) {
        $upserted = 0;
        $driver = $this->getDriver();

        if (!$data) {
            return $upserted;
        }

        if (!$driver->startTransaction()) {
            throw new QueryFailureException('Failed to start database transaction');
        }

        try {
            foreach ($data as $alias => $relatedData) {
                if (empty($data[$alias])) {
                    continue;
                }

                $relation = $this->getRelation($alias);
                $relatedRepo = $relation->getRelatedRepository();
                $fk = $relation->getForeignKey();
                $rfk = $relation->getRelatedForeignKey();
                $rpk = $relatedRepo->getPrimaryKey();

                switch ($relation->getType()) {
                    // Append the foreign key with the current ID
                    case Relation::ONE_TO_ONE:
                        $relatedData[$rfk] = $id;
                        $relatedData[$rpk] = $relatedRepo->upsert($relatedData, null, $options);

                        if (!$relatedData[$rpk]) {
                            throw new QueryFailureException(sprintf('Failed to upsert %s relational data', $alias));
                        }

                        $relatedData = $relatedRepo->data;

                        $upserted++;
                    break;

                    // Loop through and append the foreign key with the current ID
                    case Relation::ONE_TO_MANY:
                        foreach ($relatedData as $i => $hasManyData) {
                            $hasManyData[$rfk] = $id;
                            $hasManyData[$rpk] = $relatedRepo->upsert($hasManyData, null, $options);

                            if (!$hasManyData[$rpk]) {
                                throw new QueryFailureException(sprintf('Failed to upsert %s relational data', $alias));
                            }

                            $hasManyData = $relatedRepo->data;
                            $relatedData[$i] = $hasManyData;

                            $upserted++;
                        }
                    break;

                    // Loop through each set of data and upsert to gather an ID
                    // Use that foreign ID with the current ID and save in the junction table
                    case Relation::MANY_TO_MANY:
                        $junctionRepo = $relation->getJunctionRepository();
                        $jpk = $junctionRepo->getPrimaryKey();

                        foreach ($relatedData as $i => $habtmData) {
                            $junctionData = [$fk => $id];

                            // Existing record by junction foreign key
                            if (isset($habtmData[$rfk])) {
                                $foreign_id = $habtmData[$rfk];
                                unset($habtmData[$rfk]);

                                if ($habtmData) {
                                    $foreign_id = $relatedRepo->upsert($habtmData, $foreign_id, $options);
                                }

                            // Existing record by relation primary key
                            // New record
                            } else {
                                $foreign_id = $relatedRepo->upsert($habtmData, null, $options);
                                $habtmData = $relatedRepo->data;
                            }

                            if (!$foreign_id) {
                                throw new QueryFailureException(sprintf('Failed to upsert %s relational data', $alias));
                            }

                            $junctionData[$rfk] = $foreign_id;

                            // Only create the record if the junction doesn't already exist
                            $exists = $junctionRepo->select()
                                ->where($fk, $id)
                                ->where($rfk, $foreign_id)
                                ->first();

                            if (!$exists) {
                                $junctionData[$jpk] = $junctionRepo->upsert($junctionData, null, $options);

                                if (!$junctionData[$jpk]) {
                                    throw new QueryFailureException(sprintf('Failed to upsert %s junction data', $alias));
                                }
                            } else {
                                $junctionData = $exists->toArray();
                            }

                            $habtmData['Junction'] = $junctionData;
                            $relatedData[$i] = $habtmData;

                            $upserted++;
                        }
                    break;

                    // Can not save belongs to relations
                    case Relation::MANY_TO_ONE:
                        continue;
                    break;
                }

                $this->setData([$alias => $relatedData]);
            }

            $driver->commitTransaction();

        // Rollback and re-throw exception
        } catch (Exception $e) {
            $driver->rollbackTransaction();

            throw $e;
        }

        return $upserted;
    }*/

    /**
     * Validate the current set of data against the models validation rules.
     *
     * @return bool
     */
    public function validate() {
        $this->_errors = [];

        // No rules
        if (!$this->validate) {
            return true;
        }

        $validator = $this->getValidator();
        $validator->reset();
        $validator->addMessages($this->messages);
        $validator->setData($this->toArray());

        $event = $this->emit('model.preValidate', [$this, $validator]);
        $state = $event->getData();

        // Exit early if event has returned false
        if ($state === false) {
            return false;
        }

        $status = $validator->validate();

        $this->_errors = $validator->getErrors();
        $this->_validator = null;

        $this->emit('model.postValidate', [$this, $status]);

        return $status;
    }

    /**
     * @see \Titon\Db\Repository::decrement()
     */
    public static function decrement($id, array $fields) {
        return static::repository()->decrement($id, $fields);
    }

    /**
     * @see \Titon\Db\Repository::delete()
     */
    public static function deleteBy($id, array $options) {
        return static::repository()->delete($id, $options);
    }

    /**
     * @see \Titon\Db\Repository::deleteMany()
     */
    public static function deleteMany(Closure $conditions, array $options) {
        return static::repository()->deleteMany($conditions, $options);
    }

    /**
     * Will attempt to find a record by ID and return a model instance with data pre-filled.
     * If no record can be found, an empty model instance will be returned.
     *
     * @see \Titon\Db\Repository::read()
     *
     * @param int $id
     * @param array $options
     * @return \Titon\Model\Model
     */
    public static function find($id, array $options = []) {
        /** @type \Titon\Model\Model $instance */
        $instance = new static();

        if ($record = static::repository()->read($id, $options)) {
            if ($record instanceof Entity) {
                $record = $record->toArray();
            }

            $instance->add($record);
            $instance->_exists = true;
        }

        return $instance;
    }

    /**
     * Return a count of records in the table.
     *
     * @param \Closure $conditions
     * @return int
     */
    public static function total(Closure $conditions = null) {
        return static::select()->bindCallback($conditions)->count();
    }

    /**
     * @see \Titon\Db\Repository::increment()
     */
    public static function increment($id, array $fields) {
        return static::repository()->increment($id, $fields);
    }

    /**
     * @see \Titon\Db\Repository::create()
     */
    public static function insert(array $data, array $options = []) {
        return static::repository()->create($data, $options);
    }

    /**
     * @see \Titon\Db\Repository::createMany()
     */
    public static function insertMany(array $data, $hasPk = false) {
        return static::repository()->createMany($data, $hasPk);
    }

    /**
     * @see \Titon\Db\Repository::query()
     */
    public static function query($type) {
        return static::repository()->query($type);
    }

    /**
     * @see \Titon\Db\Repository::select()
     *
     * @return \Titon\Db\Query
     */
    public static function select() {
        return static::query(Query::SELECT)->fields(func_get_args());
    }

    /**
     * Return the direct table instance.
     *
     * @return \Titon\Db\Repository
     */
    public static function repository() {
        return static::getInstance()->getRepository();
    }

    /**
     * Truncate all rows in the database table.
     *
     * @return bool
     */
    public static function truncate() {
        return static::repository()->truncate();
    }

    /**
     * @see \Titon\Db\Repository::update()
     */
    public static function updateBy($id, array $data, array $options = []) {
        return static::repository()->update($id, $data, $options);
    }

    /**
     * @see \Titon\Db\Repository::updateMany()
     */
    public static function updateMany(array $data, Closure $conditions, array $options = []) {
        return static::repository()->updateMany($data, $conditions, $options);
    }

    /**
     * Load many-to-one relations.
     *
     * @return \Titon\Model\Model
     */
    protected function _loadBelongsTo() {
        foreach ($this->belongsTo as $alias => $relation) {
            $conditions = isset($relation['conditions']) ? $relation['conditions'] : null;
            unset($relation['conditions']);

            if (Hash::isNumeric(array_keys($relation))) {
                list($class, $foreignKey) = $relation;

            } else {
                $class = $relation['class'];
                $foreignKey = $relation['foreignKey'];
            }

            $this->belongsTo($alias, $class, $foreignKey, $conditions);
        }

        return $this;
    }

    /**
     * Load many-to-many relations.
     *
     * @return \Titon\Model\Model
     */
    protected function _loadBelongsToMany() {
        foreach ($this->belongsToMany as $alias => $relation) {
            $conditions = isset($relation['conditions']) ? $relation['conditions'] : null;
            unset($relation['conditions']);

            if (Hash::isNumeric(array_keys($relation))) {
                list($class, $junction, $foreignKey, $relatedKey) = $relation;

            } else {
                $class = $relation['class'];
                $junction = $relation['junction'];
                $foreignKey = $relation['foreignKey'];
                $relatedKey = $relation['relatedKey'];
            }

            $this->belongsToMany($alias, $class, $junction, $foreignKey, $relatedKey, $conditions);
        }

        return $this;
    }

    /**
     * Load one-to-one relations.
     *
     * @return \Titon\Model\Model
     */
    protected function _loadHasOne() {
        foreach ($this->hasOne as $alias => $relation) {
            $dependent = isset($relation['dependent']) ? $relation['dependent'] : true;
            $conditions = isset($relation['conditions']) ? $relation['conditions'] : null;
            unset($relation['dependent'], $relation['conditions']);

            if (Hash::isNumeric(array_keys($relation))) {
                list($class, $relatedKey) = $relation;

            } else {
                $class = $relation['class'];
                $relatedKey = $relation['relatedKey'];
            }

            $this->hasOne($alias, $class, $relatedKey, $dependent, $conditions);
        }

        return $this;
    }

    /**
     * Load one-to-many relations.
     *
     * @return \Titon\Model\Model
     */
    protected function _loadHasMany() {
        foreach ($this->hasMany as $alias => $relation) {
            $dependent = isset($relation['dependent']) ? $relation['dependent'] : true;
            $conditions = isset($relation['conditions']) ? $relation['conditions'] : null;
            unset($relation['dependent'], $relation['conditions']);

            if (Hash::isNumeric(array_keys($relation))) {
                list($class, $relatedKey) = $relation;

            } else {
                $class = $relation['class'];
                $relatedKey = $relation['relatedKey'];
            }

            $this->hasMany($alias, $class, $relatedKey, $dependent,  $conditions);
        }

        return $this;
    }

    /**
     * Validate that relation data is structured correctly.
     * Will only validate the top-level dimensions.
     *
     * @uses Titon\Utility\Hash
     *
     * @param array $data
     * @throws \Titon\Model\Exception\InvalidRelationStructureException
     */
    protected function _validateRelationData(array $data) {
        foreach ($this->getRelations() as $alias => $relation) {
            if (empty($data[$alias])) {
                continue;
            }

            $relatedData = $data[$alias];
            $type = $relation->getType();

            switch ($type) {
                // Only child records can be validated
                case Relation::MANY_TO_ONE:
                    continue;
                break;

                // Both require a numerical indexed array
                // With each value being an array of data
                case Relation::ONE_TO_MANY:
                case Relation::MANY_TO_MANY:
                    if (!Hash::isNumeric(array_keys($relatedData))) {
                        throw new InvalidRelationStructureException(sprintf('%s related data must be structured in a numerical multi-dimension array', $alias));
                    }

                    if ($type === Relation::MANY_TO_MANY) {
                        $isNotArray = Hash::some($relatedData, function($value) {
                            return !is_array($value);
                        });

                        if ($isNotArray) {
                            throw new InvalidRelationStructureException(sprintf('%s related data values must be structured arrays', $alias));
                        }
                    }
                break;

                // A single dimension of data
                case Relation::ONE_TO_ONE:
                    if (Hash::isNumeric(array_keys($relatedData))) {
                        throw new InvalidRelationStructureException(sprintf('%s related data must be structured in a single-dimension array', $alias));
                    }
                break;
            }
        }
    }

}