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
use Titon\Model\Exception\MassAssignmentException;
use Titon\Model\Exception\MissingPrimaryKeyException;
use Titon\Model\Exception\MissingRelationException;
use Titon\Model\Exception\RelationQueryFailureException;
use Titon\Model\Relation\ManyToMany;
use Titon\Model\Relation\ManyToOne;
use Titon\Model\Relation\OneToMany;
use Titon\Model\Relation\OneToOne;
use Titon\Utility\Inflector;
use Titon\Utility\Validator;
use \Exception;
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
                $model->_data = [];
                $model->_exists = false;

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
        $this->flush();

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
        $this->_data = [];
        $this->_exists = false;
        $this->_errors = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null) {
        $value = $default;

        // If the key already exists in the attribute list, return it immediately
        // If an accessor has been defined, run the value through the accessor before returning
        if ($this->has($key)) {
            $value = parent::get($key, $default);

            if ($method = $this->hasAccessor($key)) {
                $value = $this->{$method}($value);
            }
        }

        // If the key being accessed points to a relation, either lazy load the data or return the cached data
        if ($this->hasRelation($key)) {
            $value = $this->getRelation($key)->getResults();
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
        return (count($this->_relations) > 0);
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
     * Method that is called immediately after construction.
     */
    public function initialize() {
        $this->loadRelations();
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

        $this->getRelation($this->_aliases[$class])->link($model);

        return $this;
    }

    /**
     * Link multiple models at once.
     *
     * @return $this
     */
    public function linkMany() {
        foreach (func_get_args() as $model) {
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
        return [
            'db.preSave' => 'preSave',
            'db.postSave' => ['method' => 'postSave', 'priority' => 2], // Should be called after relations
            'db.preDelete' => 'preDelete',
            'db.postDelete' => ['method' => 'postDelete', 'priority' => 2], // Should be called after relations
            'db.preFind' => 'preFind',
            'db.postFind' => ['method' => 'postFind', 'priority' => 2], // Should be called after relations
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

        $this->getRelation($this->_aliases[$class])->unlink($model);

        return $this;
    }

    /**
     * Unlink multiple models at once.
     *
     * @return $this
     */
    public function unlinkMany() {
        foreach (func_get_args() as $model) {
            $this->unlink($model);
        }

        return $this;
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
     * @return \Titon\Model\QueryBuilder
     */
    public static function select() {
        return static::getInstance()->query(Query::SELECT)->fields(func_get_args());
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