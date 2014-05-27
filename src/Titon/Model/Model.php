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
use Titon\Model\Exception\MissingPrimaryKeyException;
use Titon\Model\Exception\MissingRelationException;
use Titon\Model\Relation\ManyToMany;
use Titon\Model\Relation\ManyToOne;
use Titon\Model\Relation\OneToMany;
use Titon\Model\Relation\OneToOne;
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
     * Relation aliases indexed by model class name.
     *
     * @type string[]
     */
    protected $_aliases = [];

    /**
     * If enabled, will delete dependent relations when the parent record is deleted.
     *
     * @type bool
     */
    protected $_cascade = true;

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
     * External models that have been linked as relationship data.
     * These links will be saved to the database alongside the current model.
     *
     * @type \Titon\Model\Model[]
     */
    protected $_links = [];

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
     * Add a relation between two models.
     *
     * @param \Titon\Model\Relation $relation
     * @return $this
     */
    public function addRelation(Relation $relation) {
        $relation->setPrimaryModel($this);

        $this->_relations[$relation->getAlias()] = $relation;
        $this->_aliases[$relation->getRelatedClass()] = $relation->getAlias();

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
     * @param array $options {
     *      @type bool $cascade     Will recursively delete relation records if `dependent` is true
     *      @type bool $atomic      Will wrap the delete query and all nested queries in a transaction
     * }
     * @return int
     * @throws \Titon\Model\Exception\MissingPrimaryKeyException
     */
    public function delete(array $options = []) {
        $options = $options + ['cascade' => true, 'atomic' => true];
        $id = $this->get($this->primaryKey);

        if (!$id) {
            throw new MissingPrimaryKeyException(sprintf('Cannot delete %s record if no ID is present', get_class($this)));
        }

        // Set cascade flag
        $this->_cascade = $options['cascade'];

        $model = $this;
        $operation = function() use ($model, $id, $options) {
            if ($count = $this->getRepository()->delete($id, $options)) {
                $model->_data = [];
                $model->_exists = false;
                $model->_cascade = true;

                return $count;
            }

            return 0;
        };

        // Wrap in a transaction if atomic
        if ($options['atomic']) {
            $count = $this->getRepository()->getDriver()->transaction($operation);
        } else {
            $count = call_user_func($operation);
        }

        return $count;
    }

    /**
     * Loop through all relations and delete dependent records using the ID as a base.
     *
     * This method should not be called directly.
     *
     * @param \Titon\Event\Event $event
     * @param int $id
     */
    public function deleteDependents(Event $event, $id) {
        if (!$this->_cascade) {
            return;
        }

        foreach ($this->getRelations() as $relation) {
            if (!$relation->isDependent()) {
                continue;
            }

            switch ($relation->getType()) {

                // Delete related records where the foreign key in the related table matches the current model
                case Relation::ONE_TO_ONE:
                case Relation::ONE_TO_MANY:
                    $relation->getRelatedModel()->getRepository()->deleteMany(function(Query $query) use ($relation, $id) {
                        $query->where($relation->getRelatedForeignKey(), $id);
                    });
                break;

                // Delete records where the foreign key in the junction table matches the current model
                case Relation::MANY_TO_MANY:
                    /** @type \Titon\Model\Relation\ManyToMany $relation */
                    $relation->getJunctionRepository()->deleteMany(function(Query $query) use ($relation, $id) {
                        $query->where($relation->getPrimaryForeignKey(), $id);
                    });
                break;

                // Parent records cannot be deleted via cascade
                case Relation::MANY_TO_ONE:
                    continue;
                break;
            }
        }
    }

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
     * Return the display field.
     *
     * @return string
     */
    public function getDisplayField() {
        return $this->displayField;
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
     * Return all linked models.
     *
     * @return \Titon\Model\Model[]
     */
    public function getLinks() {
        return $this->_links;
    }

    /**
     * Return the primary key.
     *
     * @return string
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
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
     * @param string $type
     * @return \Titon\Model\Relation[]
     */
    public function getRelations($type = null) {
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
     * Return the table name without prefix.
     *
     * @return string
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * Return the table prefix.
     *
     * @return string
     */
    public function getTablePrefix() {
        return $this->prefix;
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
     * @see \Titon\Db\Repository::hasBehavior()
     */
    public function hasBehavior($alias) {
        return $this->getRepository()->hasBehavior($alias);
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
        $this->loadRelationships();
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
     * Link an external model to the primary model. Once the primary is saved, the links will be saved as well.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     * @throws \Titon\Model\Exception\MissingRelationException
     */
    public function link(Model $model) {
        $class = get_class($model);

        if (empty($this->_aliases[$class])) {
            throw new MissingRelationException(sprintf('No relation found for %s', $class));
        }

        $alias = $this->_aliases[$class];
        $relation = $this->getRelation($alias);

        switch ($relation->getType()) {
            case Relation::MANY_TO_ONE:
                $this->_links[$alias] = $model;

                // Include the foreign key in the current data
                $this->set($relation->getPrimaryForeignKey(), $model->get($model->getPrimaryKey()));
            break;

            case Relation::ONE_TO_ONE:
                $this->_links[$alias] = $model;
            break;

            default:
                $this->_links[$alias][] = $model;
            break;
        }

        return $this;
    }

    /**
     * Load relationships by reflecting current model properties.
     *
     * @return \Titon\Model\Model
     */
    public function loadRelationships() {
        foreach ([
            Relation::MANY_TO_ONE => $this->belongsTo,
            Relation::MANY_TO_MANY => $this->belongsToMany,
            Relation::ONE_TO_ONE => $this->hasOne,
            Relation::ONE_TO_MANY => $this->hasMany
        ] as $type => $relations) {
            foreach ($relations as $alias => $relation) {
                if (is_string($relation)) {
                    $relation = ['model' => $relation];
                }

                $relation = $relation + [
                    'model' => null,
                    'foreignKey' => null,
                    'relatedForeignKey' => null,
                    'conditions' => null,
                    'dependent' => true,
                    'junction' => null
                ];

                switch ($type) {
                    case Relation::MANY_TO_ONE:
                        $this->belongsTo($alias, $relation['model'], $relation['foreignKey'], $relation['conditions']);
                    break;
                    case Relation::MANY_TO_MANY:
                        $this->belongsToMany($alias, $relation['model'], $relation['junction'], $relation['foreignKey'], $relation['relatedForeignKey'], $relation['conditions']);
                    break;
                    case Relation::ONE_TO_ONE:
                        $this->hasOne($alias, $relation['model'], $relation['relatedForeignKey'], $relation['dependent'], $relation['conditions']);
                    break;
                    case Relation::ONE_TO_MANY:
                        $this->hasMany($alias, $relation['model'], $relation['relatedForeignKey'], $relation['dependent'], $relation['conditions']);
                    break;
                }
            }
        }

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
            'db.postSave' => [
                ['method' => 'upsertRelations', 'priority' => 1], // Relations must be saved before anything else can happen
                ['method' => 'postSave', 'priority' => 2]
            ],
            'db.preDelete' => 'preDelete',
            'db.postDelete' => [
                ['method' => 'postDelete', 'priority' => 1], // Allow them to toggle the cascade if need be
                ['method' => 'deleteDependents', 'priority' => 2],
            ],
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
     * @param array $options {
     *      @type bool $validate    Will validate the current record of data before saving
     *      @type bool $atomic      Will wrap the save query and all nested queries in a transaction
     * }
     * @return int
     */
    public function save(array $options = []) {
        $options = $options + ['validate' => true, 'atomic' => true];
        $passed = $options['validate'] ? $this->validate() : true;

        // Validation failed, exit early
        if (!$passed) {
            return 0;
        }

        $model = $this;
        $operation = function() use ($model, $options) {
            if ($id = $model->getRepository()->upsert($model->toArray(), null, $options)) {
                $model->set($model->getPrimaryKey(), $id);
                $model->_exists = true;

                return $id;
            }

            $model->_exists = false;

            return 0;
        };

        // Wrap in a transaction if atomic
        if ($options['atomic']) {
            $id = $this->getRepository()->getDriver()->transaction($operation);
        } else {
            $id = call_user_func($operation);
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
     * Unlink an external model that has been tied to this model.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     * @throws \Titon\Model\Exception\MissingRelationException
     */
    public function unlink(Model $model) {
        $class = get_class($model);

        if (empty($this->_aliases[$class])) {
            throw new MissingRelationException(sprintf('No relation found for %s', $class));
        }

        $alias = $this->_aliases[$class];
        $relation = $this->getRelation($alias);

        switch ($relation->getType()) {
            case Relation::MANY_TO_ONE:
            case Relation::ONE_TO_ONE:
                if ($this->_links[$alias] === $model) {
                    unset($this->_links[$alias]);
                }
            break;

            default:
                foreach ($this->_links[$alias] as $i => $link) {
                    if ($link === $model) {
                        unset($this->_links[$alias][$i]);
                        break;
                    }
                }
            break;
        }

        return $this;
    }

    /**
     * Either update or insert related data for the primary model.
     * Each relation will handle upserting differently depending on type.
     *
     * This method should not be called directly.
     *
     * @param \Titon\Event\Event $event
     * @param int $id
     * @param string $type
     */
    public function upsertRelations(Event $event, $id, $type) {
        if ($type === Query::MULTI_INSERT) {
            return;
        }

        foreach ($this->getLinks() as $alias => $links) {
            $relation = $this->getRelation($alias);

            switch ($relation->getType()) {
                // Append the foreign key with the current ID
                case Relation::ONE_TO_ONE:
                    $links->set($relation->getRelatedForeignKey(), $id);
                    $links->save(['atomic' => false]);
                break;

                // Loop through and append the foreign key with the current ID
                case Relation::ONE_TO_MANY:
                    /** @type \Titon\Model\Model[] $links */
                    foreach ($links as $link) {
                        $link->set($relation->getRelatedForeignKey(), $id);
                        $link->save(['atomic' => false]);
                    }
                break;

                // Loop through each set of data and upsert to gather an ID
                // Use that foreign ID with the current ID and save in the junction table
                case Relation::MANY_TO_MANY:
                    // @todo
                break;

                // Do not save belongs to relations
                // We simply inherit the foreign key value during `link()`
                case Relation::MANY_TO_ONE:
                    continue;
                break;
            }
        }

        // Reset the links
        $this->_links = [];
    }

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

        // Exit early if event has returned false
        if ($event->getData() === false) {
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
    public static function deleteBy($id, array $options = []) {
        return static::repository()->delete($id, $options);
    }

    /**
     * @see \Titon\Db\Repository::deleteMany()
     */
    public static function deleteMany(Closure $conditions, array $options = []) {
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
        /** @type \Titon\Model\Model $record */
        if ($record = static::repository()->read($id, $options)) {
            $record->_exists = true;

            return $record;
        }

        return new static();
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
        return new QueryBuilder(static::repository()->query($type), static::getInstance());
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
     * @see \Titon\Db\Repository::select()
     *
     * @return \Titon\Db\Query
     */
    public static function select() {
        return static::query(Query::SELECT)->fields(func_get_args());
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

}