<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

use Titon\Common\Base;
use Titon\Model\Model;
use Titon\Model\Relation;
use Titon\Utility\Inflector;
use Titon\Utility\Path;
use \Closure;

/**
 * Provides shared functionality for relations.
 *
 * @package Titon\Model\Relation
 */
abstract class AbstractRelation extends Base implements Relation {

    /**
     * Configuration.
     *
     * @type array {
     *      @type string $alias             The alias name to join tables on
     *      @type string $class             Fully qualified class name for the current model
     *      @type string $relatedClass      Fully qualified class name for the related model
     *      @type string $foreignKey        Name of the foreign key in the current model
     *      @type string $relatedForeignKey Name of the foreign key in the related model
     *      @type bool $dependent           Is the relation dependent on the parent
     * }
     */
    protected $_config = [
        'alias' => '',
        'class' => '',
        'relatedClass' => '',
        'foreignKey' => '',
        'relatedForeignKey' => '',
        'dependent' => true
    ];

    /**
     * A callback that modifies a query.
     *
     * @type \Closure
     */
    protected $_conditions;

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
     * Cached query result for `getResults()`.
     *
     * @type \Titon\Db\Entity|\Titon\Db\EntityCollection
     */
    protected $_results;

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
     * {@inheritdoc}
     */
    public function getAlias() {
        return $this->getConfig('alias');
    }

    /**
     * {@inheritdoc}
     */
    public function getConditions() {
        return $this->_conditions;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryClass() {
        return $this->getConfig('class');
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryForeignKey() {
        return $this->getConfig('foreignKey');
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryModel() {
        return $this->_model;
    }

    /**
     * {@inheritdoc}
     */
    public function getRelatedClass() {
        return $this->getConfig('relatedClass');
    }

    /**
     * {@inheritdoc}
     */
    public function getRelatedForeignKey() {
        return $this->getConfig('relatedForeignKey');
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function isDependent() {
        return $this->getConfig('dependent');
    }

    /**
     * {@inheritdoc}
     */
    public function setAlias($alias) {
        $this->setConfig('alias', $alias);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setConditions(Closure $callback) {
        $this->_conditions = $callback;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDependent($state) {
        $this->setConfig('dependent', (bool) $state);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPrimaryClass($class) {
        $this->setConfig('class', (string) $class);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPrimaryForeignKey($key) {
        $this->setConfig('foreignKey', $key);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPrimaryModel(Model $model) {
        $this->_model = $model;
        $this->setPrimaryClass(get_class($model));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRelatedClass($class) {
        $this->setConfig('relatedClass', (string) $class);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRelatedForeignKey($key) {
        $this->setConfig('relatedForeignKey', $key);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRelatedModel(Model $model) {
        $this->_relatedModel = $model;
        $this->setRelatedClass(get_class($model));

        return $this;
    }

}