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
use Titon\Db\Query;
use Titon\Db\Table;
use Titon\Db\Traits\TableAware;
use Titon\Event\Event;
use Titon\Event\Listener;
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

        // Register events
        $table->on('model', $this);

        $this->fill($data);
        $this->setTable($table);
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
     * Fill the model with data to be sent to the database layer.
     * Only columns within $fillable will be set, unless the property is empty.
     *
     * @param array $data
     * @return \Titon\Model\Model
     */
    public function fill(array $data) {
        if ($this->fillable) {
            $data = Hash::reduce($data, $this->fillable);
        }

        $this->flush()->add($data);

        return $this;
    }

    /**
     * Empty all data in the model.
     *
     * @return \Titon\Model\Model
     */
    public function flush() {
        $this->_data = [];

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
     * @param array $options
     * @return int
     */
    public function save(array $options = []) {
        $data = $this->toArray();

        if (!$data) {
            return 0;
        }

        return $this->getTable()->upsert($data, null, $options);
    }

    /**
     * @see \Titon\Db\Table::createTable()
     */
    public static function create(array $options = [], array $attributes = []) {
        return self::getInstance()->getTable()->createTable($options, $attributes);
    }

    /**
     * @see \Titon\Db\Table::delete()
     */
    public static function delete($id, $options = true) {
        return self::getInstance()->getTable()->delete($id, $options);
    }

    /**
     * @see \Titon\Db\Table::read()
     */
    public static function find($id, $options = true, Closure $callback = null) {
        return self::getInstance()->getTable()->read($id, $options, $callback);
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
        return (bool) self::getInstance()->getTable()->query(Query::TRUNCATE)->save();
    }

    /**
     * @see \Titon\Db\Table::update()
     */
    public static function update($id, array $data, array $options = []) {
        return self::getInstance()->getTable()->update($id, $data, $options);
    }

    /**
     * @see \Titon\Db\Table::updateMany()
     */
    public static function updateMany(array $data, Closure $conditions, array $options = []) {
        return self::getInstance()->getTable()->updateMany($data, $conditions, $options);
    }

}

// Models should only have one instance
// This allows static calls to be possible for common tasks
Model::$singleton = true;