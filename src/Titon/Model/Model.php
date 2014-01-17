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
use Titon\Db\Callback;
use Titon\Db\Entity;
use Titon\Db\Query;
use Titon\Db\Table;
use Titon\Db\Traits\TableAware;
use Titon\Event\Event;
use Titon\Event\Listener;
use Titon\Event\Traits\Emittable;
use Titon\Model\Exception\MissingPrimaryKeyException;
use Titon\Utility\Hash;
use Titon\Utility\Validator;
use \Closure;
use \ArrayAccess;
use \Countable;
use \Iterator;

/**
 * @package Titon\Model
 * @method \Titon\Model\Model getInstance()
 */
class Model implements Callback, Listener, Iterator, ArrayAccess, Countable {
    use Mutable, Instanceable, TableAware, Emittable;

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
     * The Entity class to wrap results in.
     *
     * @type string
     */
    protected $entity = 'Titon\Db\Entity';

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
        $table = new Table([
            'connection' => $this->connection,
            'table' => $this->table,
            'prefix' => $this->prefix,
            'primaryKey' => $this->primaryKey,
            'displayField' => $this->displayField,
            'entity' => $this->entity
        ]);

        $table->on('model', $this);
        $this->on('model', $this);

        $this->fill($data);
        $this->setTable($table);
        $this->loadRelationships();
        $this->initialize();
    }

    /**
     * @see \Titon\Db\Table::addBehavior()
     */
    public function addBehavior(Behavior $behavior) {
        return $this->getTable()->addBehavior($behavior);
    }

    /**
     * @see \Titon\Db\Table::belongsTo()
     */
    public function belongsTo($alias, $class, $foreignKey) {
        $this->belongsTo[$alias] = [
            'class' => $class,
            'foreignKey' => $foreignKey
        ];

        return $this->getTable()->belongsTo($alias, $class, $foreignKey);
    }

    /**
     * @see \Titon\Db\Table::belongsToMany()
     */
    public function belongsToMany($alias, $class, $junction, $foreignKey, $relatedKey) {
        $this->belongsToMany[$alias] = [
            'class' => $class,
            'junction' => $junction,
            'foreignKey' => $foreignKey,
            'relatedKey' => $relatedKey
        ];

        return $this->getTable()->belongsToMany($alias, $class, $junction, $foreignKey, $relatedKey);
    }

    /**
     * Delete the record that as currently present in the model instance.
     *
     * @see \Titon\Db\Table::delete()
     *
     * @param mixed $options
     * @return int
     * @throws \Titon\Model\Exception\MissingPrimaryKeyException
     */
    public function delete($options = true) {
        $id = $this->get($this->primaryKey);

        if (!$id) {
            throw new MissingPrimaryKeyException(sprintf('Cannot delete %s record if no ID is present', get_class($this)));
        }

        if ($count = $this->getTable()->delete($id, $options)) {
            $this->remove($this->primaryKey);
            $this->_setExists(false);

            return $count;
        }

        return 0;
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
     * Return the validator errors.
     *
     * @return string[]
     */
    public function getErrors() {
        return $this->_errors;
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
     * @see \Titon\Db\Table::hasOne()
     */
    public function hasOne($alias, $class, $relatedKey) {
        $this->hasOne[$alias] = [
            'class' => $class,
            'relatedKey' => $relatedKey
        ];

        return $this->getTable()->hasOne($alias, $class, $relatedKey);
    }

    /**
     * @see \Titon\Db\Table::hasMany()
     */
    public function hasMany($alias, $class, $relatedKey) {
        $this->hasMany[$alias] = [
            'class' => $class,
            'relatedKey' => $relatedKey
        ];

        return $this->getTable()->hasMany($alias, $class, $relatedKey);
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
    public function preDelete(Event $event, $id, &$cascade) {
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
    public function preSave(Event $event, $id, array &$data) {
        return true;
    }

    /**
     * Method called before validation occurs.
     * Returning a boolean false will cease validation.
     *
     * @param \Titon\Event\Event $event
     * @param \Titon\Utility\Validator $validator
     * @return bool
     */
    public function preValidate(Event $event, Validator $validator) {
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
    public function postSave(Event $event, $id, $created = false) {
        return;
    }

    /**
     * Method called after validation occurs.
     *
     * @param \Titon\Event\Event $event
     * @param bool $passed
     */
    public function postValidate(Event $event, $passed = true) {
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
     * @see \Titon\Db\Table::upsert()
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

        if ($data && ($id = $this->getTable()->upsert($data, null, $options))) {
            $this->_setExists(true);
            $this->set($this->primaryKey, $id);
        } else {
            $this->_setExists(false);
        }

        return $id;
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
     * Return the current active record as an entity.
     *
     * @return \Titon\Db\Entity
     */
    public function toEntity() {
        $entity = $this->entity;

        return new $entity($this->toArray());
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

        $event = $this->emit('model.preValidate', [$validator]);
        $state = $event->getData();

        // Exit early if event has returned false
        if ($state === false) {
            return false;
        }

        $status = $validator->validate();

        $this->_errors = $validator->getErrors();
        $this->_validator = null;

        $this->emit('model.postValidate', [$status]);

        return $status;
    }

    /**
     * @see \Titon\Db\Table::delete()
     */
    public static function deleteBy($id, $options = true) {
        return self::table()->delete($id, $options);
    }

    /**
     * @see \Titon\Db\Table::deleteMany()
     */
    public static function deleteMany(Closure $conditions, $options = true) {
        return self::table()->deleteMany($conditions, $options);
    }

    /**
     * Will attempt to find a record by ID and return a model instance with data pre-filled.
     * If no record can be found, an empty model instance will be returned.
     *
     * @see \Titon\Db\Table::read()
     *
     * @param int $id
     * @param array $options
     * @return \Titon\Model\Model
     */
    public static function find($id, array $options = []) {
        /** @type \Titon\Model\Model $instance */
        $instance = new static();

        if ($record = self::table()->read($id, $options)) {
            if ($record instanceof Entity) {
                $record = $record->toArray();
            }

            $instance->add($record);
            $instance->_setExists(true);
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
        return self::select()->bindCallback($conditions)->count();
    }

    /**
     * @see \Titon\Db\Table::create()
     */
    public static function insert(array $data, array $options = []) {
        return self::table()->create($data, $options);
    }

    /**
     * @see \Titon\Db\Table::createMany()
     */
    public static function insertMany(array $data, $hasPk = false) {
        return self::table()->createMany($data, $hasPk);
    }

    /**
     * @see \Titon\Db\Table::query()
     */
    public static function query($type) {
        return self::table()->query($type);
    }

    /**
     * @see \Titon\Db\Table::select()
     *
     * @return \Titon\Db\Query
     */
    public static function select() {
        return self::query(Query::SELECT)->fields(func_get_args());
    }

    /**
     * Return the direct table instance.
     *
     * @return \Titon\Db\Table
     */
    public static function table() {
        return self::getInstance(get_called_class())->getTable();
    }

    /**
     * Truncate all rows in the database table.
     *
     * @return bool
     */
    public static function truncate() {
        return self::table()->truncate();
    }

    /**
     * @see \Titon\Db\Table::update()
     */
    public static function updateBy($id, array $data, array $options = []) {
        return self::table()->update($id, $data, $options);
    }

    /**
     * @see \Titon\Db\Table::updateMany()
     */
    public static function updateMany(array $data, Closure $conditions, array $options = []) {
        return self::table()->updateMany($data, $conditions, $options);
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

            $relation = $this->belongsTo($alias, $class, $foreignKey);

            if ($conditions) {
                $relation->setConditions($conditions);
            }
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

            $relation = $this->belongsToMany($alias, $class, $junction, $foreignKey, $relatedKey);

            if ($conditions) {
                $relation->setConditions($conditions);
            }
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

            $relation = $this->hasOne($alias, $class, $relatedKey);
            $relation->setDependent($dependent);

            if ($conditions) {
                $relation->setConditions($conditions);
            }
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

            $relation = $this->hasMany($alias, $class, $relatedKey);
            $relation->setDependent($dependent);

            if ($conditions) {
                $relation->setConditions($conditions);
            }
        }

        return $this;
    }

    /**
     * Set record existence. This should only be called internally!
     *
     * @param bool $state
     * @return \Titon\Model\Model
     */
    protected function _setExists($state) {
        $this->_exists = (bool) $state;

        return $this;
    }

}

// Models should only have one instance
// This allows static calls to be possible for common tasks
Model::$singleton = true;