<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Titon\Common\Base;
use Titon\Db\Query;
use Titon\Event\Event;
use Titon\Event\Listener;
use Titon\Utility\Inflector;
use Titon\Utility\Path;
use \Closure;

/**
 * Represents a relation of one table to another.
 *
 * @package Titon\Model
 */
abstract class Relation extends Base implements Listener {

    const ONE_TO_ONE = 'oneToOne'; // Has one
    const ONE_TO_MANY = 'oneToMany'; // Has many
    const MANY_TO_ONE = 'manyToOne'; // Belongs to
    const MANY_TO_MANY = 'manyToMany'; // Has and belongs to many

    /**
     * Configuration.
     *
     * @type array {
     *      @type string $alias             The alias name to join tables on
     *      @type string $class             Fully qualified class name for the current model
     *      @type string $relatedClass      Fully qualified class name for the related model
     *      @type string $foreignKey        Name of the foreign key in the current model
     *      @type string $relatedForeignKey Name of the foreign key in the related model
     * }
     */
    protected $_config = [
        'alias' => '',
        'class' => '',
        'relatedClass' => '',
        'foreignKey' => '',
        'relatedForeignKey' => ''
    ];

    /**
     * A callback that modifies a query.
     *
     * @type \Closure
     */
    protected $_conditions;

    /**
     * A query to eager load related records.
     *
     * @type \Titon\Model\QueryBuilder
     */
    protected $_eagerQuery;

    /**
     * Related models that have been linked to the primary model.
     * These links will be saved to the database alongside the primary model.
     *
     * @type \Titon\Model\Model[]
     */
    protected $_links = [];

    /**
     * Primary model instance.
     *
     * @type \Titon\Model\Model
     */
    protected $_model;

    /**
     * Related model instance.
     *
     * @type \Titon\Model\Model
     */
    protected $_relatedModel;

    /**
     * Store the alias and class name.
     *
     * @param string $alias
     * @param string $class
     * @param array $config
     */
    public function __construct($alias, $class, array $config = []) {
        parent::__construct($config);

        $this->setAlias($alias);
        $this->setRelatedClass($class);
    }

    /**
     * Generate a foreign key column name by inflecting a class name.
     *
     * @param string $class
     * @return string
     */
    public function buildForeignKey($class) {
        if (strpos($class, '\\') !== false) {
            $class = Path::className($class);
        }

        return Inflector::underscore($class) . '_id';
    }

    /**
     * Delete all related records that are dependent on the primary record.
     *
     * This method is called through a `db.postDelete` event and should not be called directly.
     *
     * @param \Titon\Event\Event $event
     * @param int|int[] $id
     * @param int $count
     */
    abstract public function deleteDependents(Event $event, $id, $count);

    /**
     * Return a foreign key either for the primary or related model.
     * If no foreign key is defined, automatically inflect one and set it.
     *
     * @param string $config
     * @param string $class
     * @return string
     */
    public function detectForeignKey($config, $class) {
        $foreignKey = $this->getConfig($config);

        if (!$foreignKey) {
            $foreignKey = $this->buildForeignKey($class);

            $this->setConfig($config, $foreignKey);
        }

        return $foreignKey;
    }

    /**
     * Eager load a set of related records by passing in a query to later execute.
     *
     * @param \Titon\Model\QueryBuilder $query
     * @return $this
     */
    public function eagerLoad(QueryBuilder $query) {
        $this->_eagerQuery = $query;

        return $this;
    }

    /**
     * Query the database for records that supply the current relationship.
     *
     * @return \Titon\Model\Model|\Titon\Model\Model[]
     */
    abstract public function fetchRelation();

    /**
     * Return the relation alias name.
     *
     * @return string
     */
    public function getAlias() {
        return $this->getConfig('alias');
    }

    /**
     * Return the query conditions.
     *
     * @return \Closure
     */
    public function getConditions() {
        return $this->_conditions;
    }

    /**
     * Return the eager loading query if available.
     *
     * @return \Titon\Model\QueryBuilder
     */
    public function getEagerQuery() {
        return $this->_eagerQuery;
    }

    /**
     * Return all linked models.
     *
     * @return \Titon\Model\Model[]
     */
    public function getLinked() {
        return $this->_links;
    }

    /**
     * Return the primary model class name.
     *
     * @return string
     */
    public function getPrimaryClass() {
        return $this->getConfig('class');
    }

    /**
     * Return the name of the foreign key for the primary model.
     *
     * @return string
     */
    public function getPrimaryForeignKey() {
        return $this->getConfig('foreignKey');
    }

    /**
     * Return a primary model object.
     *
     * @return \Titon\Model\Model
     */
    public function getPrimaryModel() {
        if ($model = $this->_model) {
            return $model;
        }

        $class = $this->getPrimaryClass();

        if (!$class) {
            return null;
        }

        $this->setPrimaryModel(new $class());

        return $this->_model;
    }

    /**
     * Return the related model class name.
     *
     * @return string
     */
    public function getRelatedClass() {
        return $this->getConfig('relatedClass');
    }

    /**
     * Return the name of the related foreign key.
     *
     * @return string
     */
    public function getRelatedForeignKey() {
        return $this->getConfig('relatedForeignKey');
    }

    /**
     * Return a related model object.
     *
     * @return \Titon\Model\Model
     */
    public function getRelatedModel() {
        if ($model = $this->_relatedModel) {
            return $model;
        }

        $class = $this->getRelatedClass();

        $this->setRelatedModel(new $class());

        return $this->_relatedModel;
    }

    /**
     * Return the type of relation.
     *
     * @return string
     */
    abstract public function getType();

    /**
     * Link a related model to the primary model.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function link(Model $model) {
        $this->_links[] = $model;

        return $this;
    }

    /**
     * If the related model has been queried for eager loading, fetch the related records using the defined query.
     * Once results are fetched, merge in the related results into the parent results.
     *
     * This method is called through a `db.postFind` event and should not be called directly.
     *
     * @param \Titon\Event\Event $event
     * @param array $results
     * @param string $finder
     */
    abstract public function loadRelations(Event $event, array &$results, $finder);

    /**
     * Create a new query for the related model.
     *
     * @param string $type
     * @return \Titon\Model\QueryBuilder
     */
    public function query($type) {
        return $this->getRelatedModel()->query($type);
    }

    /**
     * {@inheritdoc}
     */
    public function registerEvents() {
        // Repository callbacks use priority 1, so we should use 2
        return [
            'db.postSave' => ['method' => 'saveLinked', 'priority' => 2], // Relations must be saved before anything else happens
            'db.postDelete' => ['method' => 'deleteDependents', 'priority' => 2],
            'db.postFind' => ['method' => 'loadRelations', 'priority' => 2], // Relations should exist before anything else happens
        ];
    }

    /**
     * Save all the linked models. If a save fails, throw an exception to break out of any transactions.
     *
     * This method is called through a `db.postSave` event and should not be called directly.
     *
     * @param \Titon\Event\Event $event
     * @param int|int[] $id
     * @param int $count
     * @throws \Titon\Model\Exception\RelationQueryFailureException
     */
    abstract public function saveLinked(Event $event, $id, $count);

    /**
     * Set the alias name.
     *
     * @param string $alias
     * @return $this
     */
    public function setAlias($alias) {
        $this->setConfig('alias', $alias);

        return $this;
    }

    /**
     * Set the query conditions for this relation.
     *
     * @param \Closure $callback
     * @return $this
     */
    public function setConditions(Closure $callback) {
        $this->_conditions = $callback;

        return $this;
    }

    /**
     * Set the primary model class name.
     *
     * @param string $class
     * @return $this
     */
    public function setPrimaryClass($class) {
        $this->setConfig('class', (string) $class);

        return $this;
    }

    /**
     * Set the foreign key for the primary model.
     *
     * @param string $key
     * @return $this
     */
    public function setPrimaryForeignKey($key) {
        $this->setConfig('foreignKey', $key);

        return $this;
    }

    /**
     * Set the primary model object.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function setPrimaryModel(Model $model) {
        $this->_model = $model;
        $this->setPrimaryClass(get_class($model));

        return $this;
    }

    /**
     * Set the related model class name.
     *
     * @param string $class
     * @return $this
     */
    public function setRelatedClass($class) {
        $this->setConfig('relatedClass', (string) $class);

        return $this;
    }

    /**
     * Set the foreign key for the related table.
     *
     * @param string $key
     * @return $this
     */
    public function setRelatedForeignKey($key) {
        $this->setConfig('relatedForeignKey', $key);

        return $this;
    }

    /**
     * Set the related model object.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function setRelatedModel(Model $model) {
        $this->_relatedModel = $model;
        $this->setRelatedClass(get_class($model));

        return $this;
    }

    /**
     * Unlink a related model that has been tied to the primary model.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function unlink(Model $model) {
        foreach ($this->getLinked() as $i => $link) {
            if ($link === $model) {
                unset($this->_links[$i]);
            }
        }

        // Reorder indices
        $this->_links = array_values($this->_links);

        return $this;
    }

}