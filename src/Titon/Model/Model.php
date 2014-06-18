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
use Titon\Db\Finder;
use Titon\Db\Query;
use Titon\Db\Repository;
use Titon\Db\RepositoryAware;
use Titon\Event\Event;
use Titon\Event\Listener;
use Titon\Event\Traits\Emittable;
use Titon\Model\Exception\MassAssignmentException;
use Titon\Model\Exception\MissingPrimaryKeyException;
use Titon\Model\Exception\MissingRelationException;
use Titon\Model\Relation\ManyToMany;
use Titon\Model\Relation\ManyToOne;
use Titon\Model\Relation\OneToMany;
use Titon\Model\Relation\OneToOne;
use Titon\Type\Contract\Arrayable;
use Titon\Type\Contract\Jsonable;
use Titon\Utility\Hash;
use Titon\Utility\Inflector;
use Titon\Utility\Validator;
use \ArrayIterator;
use \Serializable;
use \JsonSerializable;
use \Exception;
use \IteratorAggregate;
use \ArrayAccess;
use \Countable;
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
class Model implements Listener, Serializable, JsonSerializable, IteratorAggregate, ArrayAccess, Countable, Arrayable, Jsonable {
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
     * Reserved attributes that will not flag attribute changes.
     * Will include all relation aliases.
     *
     * @type array
     */
    protected $reserved = ['junction'];

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
     * Map of key value pairs associated with this model record.
     * Is set during a database find, or used for a database save.
     *
     * @type array
     */
    protected $_attributes = [];

    /**
     * Has the model data changed?
     *
     * @type bool
     */
    protected $_changed = false;

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
     * The original set of data passed through the constructor.
     * This array is used to watch for changes.
     *
     * @type array
     */
    protected $_original = [];

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
     * Optionally set a record of data into the model.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = []) {
        $this->initialize();
        $this->mapData($attributes);
    }

    /**
     * Make clone publicly available.
     */
    public function __clone() {
    }

    /**
     * Magic method for get().
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key) {
        return $this->get($key);
    }

    /**
     * Magic method for set().
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value) {
        $this->set($key, $value);
    }

    /**
     * Magic method for has().
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key) {
        return $this->has($key);
    }

    /**
     * Magic method for remove().
     *
     * @param string $key
     */
    public function __unset($key) {
        $this->remove($key);
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
        $this->reserved[] = $relation->getAlias();

        $this->on('relation', $relation);

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
     * Return the changed state.
     *
     * @return bool
     */
    public function changed() {
        return $this->_changed;
    }

    /**
     * Return the count of the array.
     *
     * @return int
     */
    public function count() {
        return count($this->_attributes);
    }

    /**
     * Delete the record that is currently present in the model instance.
     *
     * @see \Titon\Db\Repository::delete()
     *
     * @param array $options {
     *      @type bool $atomic      Will wrap the delete query and all nested queries in a transaction
     * }
     * @return int
     * @throws \Titon\Model\Exception\MissingPrimaryKeyException
     */
    public function delete(array $options = []) {
        $options = $options + ['atomic' => true];
        $id = $this->get($this->primaryKey);

        if (!$id) {
            throw new MissingPrimaryKeyException(sprintf('Cannot delete %s record if no ID is present', get_class($this)));
        }

        $model = $this;
        $operation = function() use ($model, $id, $options) {
            if ($count = $model->getRepository()->delete($id, $options)) {
                $model->flush();

                return $count;
            }

            return 0;
        };

        // Wrap in a transaction if atomic
        if ($options['atomic']) {
            try {
                $count = $this->getRepository()->getDriver()->transaction($operation);
            } catch (Exception $e) {
                return 0;
            }
        } else {
            $count = call_user_func($operation);
        }

        return $count;
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
     * @throws \Titon\Model\Exception\MassAssignmentException
     */
    public function fill(array $data) {
        if ($this->isFullyGuarded()) {
            throw new MassAssignmentException(sprintf('Cannot assign attributes as %s is locked', get_class($this)));
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
        $this->_attributes = [];
        $this->_original = [];
        $this->_changed = false;
        $this->_exists = false;
        $this->_errors = [];
        $this->_validator = null;

        return $this;
    }

    /**
     * Get an attribute on the model. If the attribute points to a relation,
     * fetch the related records through the relation object.
     * If an accessor is defined, pass the non-relation attribute through it.
     *
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        $value = null;

        // If the key already exists in the attribute list, return it immediately
        if ($this->has($key)) {
            $value = $this->_attributes[$key];
        }

        // If the key being accessed points to a relation, either lazy load the data or return the cached data
        if (!$value && $this->hasRelation($key)) {
            $value = $this->getRelation($key)->fetchRelation();

            $this->set($key, $value);

        // If an accessor has been defined, run the value through the accessor before returning
        } else if ($method = $this->hasAccessor($key)) {
            $value = $this->{$method}($value);
        }

        return $value;
    }

    /**
     * Return a relation alias by checking against a fully qualified class name.
     *
     * @param string $class
     * @return string
     * @throws \Titon\Model\Exception\MissingRelationException
     */
    public function getAlias($class) {
        if (empty($this->_aliases[$class])) {
            throw new MissingRelationException(sprintf('No relation found for %s', $class));
        }

        return $this->_aliases[$class];
    }

    /**
     * Return an array of data that only includes attributes that have changed.
     *
     * @return array
     */
    public function getChanged() {
        $attributes = [];

        if ($this->changed()) {
            $attributes = array_diff_assoc($this->_attributes, $this->_original);

            // Remove reserved attributes
            $attributes = Hash::exclude($attributes, $this->reserved);
        }

        return $attributes;
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
     * Return an iterator.
     *
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->_attributes);
    }

    /**
     * Return the original data set.
     *
     * @return array
     */
    public function getOriginal() {
        return $this->_original;
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
     * Check if an attribute is set. Will return true for null values.
     *
     * @param string $key
     * @return bool
     */
    public function has($key) {
        return array_key_exists($key, $this->_attributes);
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
     * @param \Closure $conditions
     * @return $this
     */
    public function hasOne($alias, $class, $relatedKey = null, Closure $conditions = null) {
        $relation = (new OneToOne($alias, $class))
            ->setRelatedForeignKey($relatedKey);

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
     * @param \Closure $conditions
     * @return $this
     */
    public function hasMany($alias, $class, $relatedKey = null, Closure $conditions = null) {
        $relation = (new OneToMany($alias, $class))
            ->setRelatedForeignKey($relatedKey);

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
        return !empty($this->_relations);
    }

    /**
     * Return the value of the ID (primary key) attribute.
     *
     * @return string
     */
    public function id() {
        return $this->get($this->primaryKey);
    }

    /**
     * Method used to bootstrap the model.
     */
    public function initialize() {
        $this->loadRelations();
    }

    /**
     * Check to see if a field has changed (is dirty).
     *
     * @param string $key
     * @return bool
     */
    public function isDirty($key) {
        if (empty($this->_original[$key]) && empty($this->_attributes[$key])) {
            return false; // No data in either

        } else if (empty($this->_original[$key]) && isset($this->_attributes[$key])) {
            return true; // No original to compare against

        } else if ($this->_attributes[$key] !== $this->_original[$key]) {
            return true; // Current value is different than original
        }

        return false;
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
     * Return the values for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize() {
        return $this->toArray();
    }

    /**
     * Link an external model to the primary model. Once the primary is saved, the links will be saved as well.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     * @throws \Titon\Model\Exception\MissingRelationException
     */
    public function link(Model $model) {
        $this->getRelation($this->getAlias(get_class($model)))->link($model);

        return $this;
    }

    /**
     * Link multiple models at once.
     *
     * @return $this
     */
    public function linkMany() {
        $models = func_get_args();

        if (is_array($models[0])) {
            $models = $models[0];
        }

        foreach ($models as $model) {
            $this->link($model);
        }

        return $this;
    }

    /**
     * Load relationships by reflecting current model properties.
     *
     * @return \Titon\Model\Model
     */
    public function loadRelations() {
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
                        $this->hasOne($alias, $relation['model'], $relation['relatedForeignKey'], $relation['conditions']);
                    break;
                    case Relation::ONE_TO_MANY:
                        $this->hasMany($alias, $relation['model'], $relation['relatedForeignKey'], $relation['conditions']);
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * When data is mapped through the constructor, set the exists flag if necessary,
     * and save the original data state to monitor for changes.
     *
     * @param array $data
     * @return $this
     */
    public function mapData(array $data) {
        $this->flush();

        if (!empty($data[$this->primaryKey])) {
            $this->_exists = true;
        }

        $this->_original = Hash::exclude($data, $this->reserved);
        $this->_attributes = $data;

        return $this;
    }

    /**
     * Alias method for get().
     *
     * @param string $key
     * @return mixed
     */
    public function offsetGet($key) {
        return $this->get($key);
    }

    /**
     * Alias method for set().
     *
     * @param string $key
     * @param mixed $value
     */
    public function offsetSet($key, $value) {
        $this->set($key, $value);
    }

    /**
     * Alias method for has().
     *
     * @param string $key
     * @return bool
     */
    public function offsetExists($key) {
        return $this->has($key);
    }

    /**
     * Alias method for remove().
     *
     * @param string $key
     */
    public function offsetUnset($key) {
        $this->remove($key);
    }

    /**
     * Callback triggered before a delete operation occurs.
     * Returning false will abort the deletion.
     *
     * @param \Titon\Event\Event $event
     * @param \Titon\Db\Query $query
     * @param int $id
     * @return mixed
     */
    public function preDelete(Event $event, Query $query, $id) {
        return true;
    }

    /**
     * Callback triggered before a find operation occurs.
     * Returning false will abort the find.
     *
     * @param \Titon\Event\Event $event
     * @param \Titon\Db\Query $query
     * @param string $finder
     * @return mixed
     */
    public function preFind(Event $event, Query $query, $finder) {
        return true;
    }

    /**
     * Callback triggered before an insert or update operation occurs.
     * Returning false will abort the save.
     *
     * @param \Titon\Event\Event $event
     * @param \Titon\Db\Query $query
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function preSave(Event $event, Query $query, $id, array &$data) {
        return true;
    }

    /**
     * Callback triggered before validation occurs.
     * Returning false will cease validation.
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
     * Callback triggered after a delete operation occurs.
     *
     * @param \Titon\Event\Event $event
     * @param int $id
     * @param int $count
     * @return mixed
     */
    public function postDelete(Event $event, $id, $count) {
        return;
    }

    /**
     * Callback triggered after a find operation occurs.
     *
     * @param \Titon\Event\Event $event
     * @param array $results
     * @param string $finder
     * @return mixed
     */
    public function postFind(Event $event, array &$results, $finder) {
        return;
    }

    /**
     * Callback triggered after an insert or update operation occurs.
     *
     * @param \Titon\Event\Event $event
     * @param int $id
     * @param int $count
     * @return mixed
     */
    public function postSave(Event $event, $id, $count) {
        return;
    }

    /**
     * Callback triggered after validation occurs.
     *
     * @param \Titon\Event\Event $event
     * @param \Titon\Model\Model $model
     * @param bool $passed
     */
    public function postValidate(Event $event, Model $model, $passed = true) {
        return;
    }

    /**
     * Create a new repository query and wrap the query in a builder class.
     * This builder will extend the query and provide model level functionality.
     *
     * @param string $type
     * @return \Titon\Model\QueryBuilder
     */
    public function query($type) {
        return new QueryBuilder($this->getRepository()->query($type), $this);
    }

    /**
     * {@inheritdoc}
     */
    public function registerEvents() {
        // Repository callbacks use priority 1, relations use 2, so we should use 3
        return [
            'db.preSave' => 'preSave',
            'db.postSave' => ['method' => 'postSave', 'priority' => 3], // Should be called after relations
            'db.preDelete' => 'preDelete',
            'db.postDelete' => ['method' => 'postDelete', 'priority' => 3], // Should be called after relations
            'db.preFind' => 'preFind',
            'db.postFind' => ['method' => 'postFind', 'priority' => 3], // Should be called after relations
            'model.preValidate' => 'preValidate',
            'model.postValidate' => 'postValidate'
        ];
    }

    /**
     * Remove an attribute defined by key.
     *
     * @param string $key
     * @return $this
     */
    public function remove($key) {
        unset($this->_attributes[$key]);

        return $this;
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
     *      @type bool $force       Will save all attributes at once instead of only changed attributes
     * }
     * @return int
     */
    public function save(array $options = []) {
        $options = $options + ['validate' => true, 'atomic' => true, 'force' => false];
        $passed = $options['validate'] ? $this->validate() : true;

        // Validation failed, exit early
        if (!$passed) {
            return 0;
        }

        // Save fields that have changed, else save them all
        $data = $this->getChanged();

        if (!$data || $options['force']) {
            $data = $this->toArray();
        }

        // Be sure to persist the ID for updates
        if (empty($data[$this->primaryKey]) && ($id = $this->id())) {
            $data[$this->primaryKey] = $id;
        }

        // Upsert the record and modify flags on success
        $model = $this;
        $operation = function() use ($model, $data, $options) {
            if ($id = $model->getRepository()->upsert($data, null, $options)) {
                $model->mapData($model->_attributes);
                $model->set($model->getPrimaryKey(), $id);
                $model->_exists = true;
                $model->_changed = false;

                return $id;
            }

            $model->_exists = false;

            return 0;
        };

        // Wrap in a transaction if atomic
        if ($options['atomic']) {
            try {
                $id = $this->getRepository()->getDriver()->transaction($operation);
            } catch (Exception $e) {
                return 0;
            }
        } else {
            $id = call_user_func($operation);
        }

        return $id;
    }

    /**
     * Set an attribute defined by key. If the key does not point to a relation,
     * pass the value through a mutator and set a changed flag.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function set($key, $value = null) {

        // Do not trigger state changes for relations
        if (!$this->hasRelation($key)) {

            // Only flag changed if the value is different
            if ($value != $this->get($key) && !in_array($key, $this->reserved)) {
                $this->_changed = true;
            }

            // Run the value through a mutator but do not set the mutator key
            if ($method = $this->hasMutator($key)) {
                $this->{$method}($value);

                return $this;
            }
        }

        $this->_attributes[$key] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRepository(Repository $repository) {
        $repository->on('model', $this);

        foreach ($this->getRelations() as $relation) {
            $repository->on('relation', $relation);
        }

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

        // Only set model events if we plan on validating
        $this->on('model', $this);

        return $this;
    }

    /**
     * Serialize the configuration.
     *
     * @return string
     */
    public function serialize() {
        return serialize($this->_attributes);
    }

    /**
     * Return the model attributes as an array.
     *
     * @return array
     */
    public function toArray() {
        return array_map(function($value) {
            return ($value instanceof Arrayable) ? $value->toArray() : $value;
        }, $this->_attributes);
    }

    /**
     * Return the model attributes as a JSON string.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0) {
        return json_encode($this, $options);
    }

    /**
     * Unlink an external model that has been tied to this model.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     * @throws \Titon\Model\Exception\MissingRelationException
     */
    public function unlink(Model $model) {
        $this->getRelation($this->getAlias(get_class($model)))->unlink($model);

        return $this;
    }

    /**
     * Unlink multiple models at once.
     *
     * @return $this
     */
    public function unlinkMany() {
        $models = func_get_args();

        if (is_array($models[0])) {
            $models = $models[0];
        }

        foreach ($models as $model) {
            $this->unlink($model);
        }

        return $this;
    }

    /**
     * Reconstruct the data once unserialized.
     *
     * @param string $data
     */
    public function unserialize($data) {
        $this->__construct(unserialize($data));
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

        // Build the validator
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
     * Return the average value for a field based on all records in the database.
     *
     * @see \Titon\Db\Repository::aggregate()
     *
     * @param string $field
     * @return int
     */
    public static function avg($field) {
        return static::select()->avg($field);
    }

    /**
     * Create a new model instance, fill with data, and attempt to save.
     * Return the model instance on success, or a null on failure.
     *
     * @see \Titon\Db\Repository::create()
     *
     * @param array $data
     * @param array $options
     * @return \Titon\Model\Model
     */
    public static function create(array $data, array $options = []) {
        /** @type \Titon\Model\Model $model */
        $model = new static();
        $model->fill($data);

        if ($model->save($options)) {
            return $model;
        }

        return null;
    }

    /**
     * Decrement a field on a single record (or multiple if a Closure is passed) using a stepped value.
     *
     * @see \Titon\Db\Repository::decrement()
     *
     * @param int|\Closure $id
     * @param array $fields
     * @return int - Number of rows affected
     */
    public static function decrement($id, array $fields) {
        return static::repository()->decrement($id, $fields);
    }

    /**
     * Similar to `findBy()`, but will attempt to find a record by ID.
     *
     * @param int $id
     * @param array $options
     * @return \Titon\Model\Model
     */
    public static function find($id, array $options = []) {
        return static::findBy(static::getInstance()->getPrimaryKey(), $id, $options);
    }

    /**
     * Will attempt to find a record by a field's value and return a model instance with data pre-filled.
     * If no record can be found, an empty model instance will be returned.
     *
     * @param string $field
     * @param mixed $value
     * @param array $options
     * @return \Titon\Model\Model
     */
    public static function findBy($field, $value, array $options = []) {
        if ($record = static::select()->where($field, $value)->first($options)) {
            return $record;
        }

        return new static();
    }

    /**
     * Increment a field on a single record (or multiple if a Closure is passed) using a stepped value.
     *
     * @see \Titon\Db\Repository::increment()
     *
     * @param int|\Closure $id
     * @param array $fields
     * @return int - Number of rows affected
     */
    public static function increment($id, array $fields) {
        return static::repository()->increment($id, $fields);
    }

    /**
     * Return the maximum value for a field based on all records in the database.
     *
     * @see \Titon\Db\Repository::aggregate()
     *
     * @param string $field
     * @return int
     */
    public static function max($field) {
        return static::select()->max($field);
    }

    /**
     * Return the minimum value for a field based on all records in the database.
     *
     * @see \Titon\Db\Repository::aggregate()
     *
     * @param string $field
     * @return int
     */
    public static function min($field) {
        return static::select()->min($field);
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
     * Create a new SELECT query that is wrapped with the models query builder interface.
     * Optionally set a list of fields to select.
     *
     * @see \Titon\Db\Repository::select()
     *
     * @param array $fields
     * @return \Titon\Model\QueryBuilder
     */
    public static function select(array $fields = []) {
        return static::getInstance()->query(Query::SELECT)->fields($fields);
    }

    /**
     * Return the sum of values for a field based on all records in the database.
     *
     * @see \Titon\Db\Repository::aggregate()
     *
     * @param string $field
     * @return int
     */
    public static function sum($field) {
        return static::select()->sum($field);
    }

    /**
     * Return the total count of all records in the database.
     *
     * @see \Titon\Db\Repository::aggregate()
     *
     * @return int
     */
    public static function total() {
        return static::select()->count();
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
     * Update a model by ID by finding the record in the database, filling the data, and attempting to save.
     * Return the model instance on success, or a null on failure.
     *
     * @see \Titon\Db\Repository::update()
     *
     * @param int $id
     * @param array $data
     * @param array $options
     * @return \Titon\Model\Model
     */
    public static function update($id, array $data, array $options = []) {
        $model = static::find($id);

        if ($model->exists()) {
            $model->fill($data);

            if ($model->save($options)) {
                return $model;
            }
        }

        return null;
    }

}