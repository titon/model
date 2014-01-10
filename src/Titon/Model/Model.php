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
use Titon\Model\Exception\MissingPrimaryKeyException;
use Titon\Utility\Hash;
use \Closure;
use \ArrayAccess;
use \Countable;
use \Iterator;

/**
 * @package Titon\Model
 * @method \Titon\Model\Model getInstance()
 */
class Model implements Callback, Listener, Iterator, ArrayAccess, Countable {
    use Mutable, Instanceable, TableAware;

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
    protected $validation = [];

    /**
     * List of validation errors.
     *
     * @type array
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
        $this->fill($data);

        // Load table
        $table = new Table([
            'connection' => $this->connection,
            'table' => $this->table,
            'prefix' => $this->prefix,
            'primaryKey' => $this->primaryKey,
            'displayField' => $this->displayField,
            'entity' => $this->entity
        ]);

        $table->on('model', $this);

        $this->setTable($table);

        // Set relations
        //$this->_loadBelongsTo();
        //$this->_loadBelongsToMany();
        //$this->_loadHasOne();
        //$this->_loadHasMany();

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
        return $this->getTable()->belongsTo($alias, $class, $foreignKey);
    }

    /**
     * @see \Titon\Db\Table::belongsToMany()
     */
    public function belongsToMany($alias, $class, $junction, $foreignKey, $relatedKey) {
        return $this->getTable()->belongsToMany($alias, $class, $junction, $foreignKey, $relatedKey);
    }

    /**
     * Delete the record that as currently present in the model instance.
     *
     * @see \Titon\Db\Table::delete()
     *
     * @param mixed $options
     * @return bool
     * @throws \Titon\Model\Exception\MissingPrimaryKeyException
     */
    public function delete($options = true) {
        $id = $this->get($this->primaryKey);

        if (!$id) {
            throw new MissingPrimaryKeyException(sprintf('Cannot delete %s record if no ID is present', get_class($this)));
        }

        if ($this->getTable()->delete($id, $options)) {
            $this->remove($this->primaryKey);
            $this->setExists(false);

            return true;
        }

        return false;
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
     * @see \Titon\Db\Table::hasOne()
     */
    public function hasOne($alias, $class, $relatedKey) {
        return $this->getTable()->hasOne($alias, $class, $relatedKey);
    }

    /**
     * @see \Titon\Db\Table::hasMany()
     */
    public function hasMany($alias, $class, $relatedKey) {
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
     * {@inheritdoc}
     */
    public function preDelete(Event $event, $id, &$cascade) {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function preFetch(Event $event, Query $query, $fetchType) {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function preSave(Event $event, $id, array &$data) {
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
    public function postFetch(Event $event, array &$results, $fetchType) {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function postSave(Event $event, $id, $created = false) {
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
            'db.preFetch' => 'preFetch',
            'db.postFetch' => 'postFetch'
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
        $data = $this->toArray();
        $id = 0;

        if ($data && ($id = $this->getTable()->upsert($data, null, $options))) {
            $this->setExists(true);
            $this->set($this->primaryKey, $id);
        } else {
            $this->setExists(false);
        }

        return $id;
    }

    /**
     * Set record existence. This should only be called internally!
     *
     * @param bool $state
     * @return \Titon\Model\Model
     */
    public function setExists($state) {
        $this->_exists = (bool) $state;

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
     * @see \Titon\Db\Table::delete()
     */
    public static function deleteBy($id, $options = true) {
        return self::getInstance()->getTable()->delete($id, $options);
    }

    /**
     * Will attempt to find a record by ID and return a model instance with data pre-filled.
     * If no record can be found, an empty model instance will be returned.
     *
     * @see \Titon\Db\Table::read()
     *
     * @param int $id
     * @param mixed $options
     * @param \Closure $callback
     * @return \Titon\Model\Model
     */
    public static function find($id, $options = true, Closure $callback = null) {
        /** @type \Titon\Model\Model $instance */
        $instance = new static();

        if ($record = self::getInstance()->getTable()->read($id, $options, $callback)) {
            if ($record instanceof Entity) {
                $record = $record->toArray();
            }

            $instance->add($record);
            $instance->setExists(true);
        }

        return $instance;
    }

    /**
     * @see \Titon\Db\Table::create()
     */
    public static function insert(array $data, array $options = []) {
        return self::getInstance()->getTable()->create($data, $options);
    }

    /**
     * @see \Titon\Db\Table::createMany()
     */
    public static function insertMany(array $data, $hasPk = false) {
        return self::getInstance()->getTable()->createMany($data, $hasPk);
    }

    /**
     * @see \Titon\Db\Table::query()
     */
    public function query($type) {
        return self::getInstance()->getTable()->query($type);
    }

    /**
     * @see \Titon\Db\Table::select()
     */
    public static function select() {
        return self::getInstance()->getTable()->select(func_get_args());
    }

    /**
     * Truncate all rows in the database table.
     *
     * @return bool
     */
    public static function truncate() {
        return (bool) self::query(Query::TRUNCATE)->save();
    }

    /**
     * @see \Titon\Db\Table::update()
     */
    public static function updateBy($id, array $data, array $options = []) {
        return self::getInstance()->getTable()->update($id, $data, $options);
    }

    /**
     * @see \Titon\Db\Table::updateMany()
     */
    public static function updateMany(array $data, Closure $conditions, array $options = []) {
        return self::getInstance()->getTable()->updateMany($data, $conditions, $options);
    }

    /**
     * Load many-to-one relations.
     */
    protected function _loadBelongsTo() {
        foreach ($this->belongsTo as $alias => $relation) {
            if (Hash::isNumeric(array_keys($relation))) {
                list($class, $foreignKey) = $relation;

            } else {
                $class = $relation['class'];
                $foreignKey = $relation['foreignKey'];
            }

            $this->belongsTo($alias, (new $class)->getTable(), $foreignKey);
        }
    }

    /**
     * Load many-to-many relations.
     */
    protected function _loadBelongsToMany() {
        foreach ($this->belongsToMany as $alias => $relation) {
            if (Hash::isNumeric(array_keys($relation))) {
                list($class, $junction, $foreignKey, $relatedKey) = $relation;

            } else {
                $class = $relation['class'];
                $junction = $relation['junction'];
                $foreignKey = $relation['foreignKey'];
                $relatedKey = $relation['relatedKey'];
            }

            $this->belongsToMany($alias, (new $class)->getTable(), $junction, $foreignKey, $relatedKey);
        }
    }

    /**
     * Load one-to-one relations.
     */
    protected function _loadHasOne() {
        foreach ($this->hasOne as $alias => $relation) {
            $dependent = isset($relation['dependent']) ? $relation['dependent'] : true;
            unset($relation['dependent']);

            if (Hash::isNumeric(array_keys($relation))) {
                list($class, $relatedKey) = $relation;

            } else {
                $class = $relation['class'];
                $relatedKey = $relation['relatedKey'];
            }

            //print_r(new $class);

            $relation = $this->hasOne($alias, (new $class)->getTable(), $relatedKey);
            $relation->setDependent($dependent);
        }
    }

    /**
     * Load one-to-many relations.
     */
    protected function _loadHasMany() {
        foreach ($this->hasMany as $alias => $relation) {
            $dependent = isset($relation['dependent']) ? $relation['dependent'] : true;
            unset($relation['dependent']);

            if (Hash::isNumeric(array_keys($relation))) {
                list($class, $relatedKey) = $relation;

            } else {
                $class = $relation['class'];
                $relatedKey = $relation['relatedKey'];
            }

            $relation = $this->hasMany($alias, (new $class)->getTable(), $relatedKey);
            $relation->setDependent($dependent);
        }
    }

}

// Models should only have one instance
// This allows static calls to be possible for common tasks
Model::$singleton = true;