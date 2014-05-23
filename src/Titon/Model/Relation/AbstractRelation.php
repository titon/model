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
     *      @type string $class             Fully qualified class name for the related model
     *      @type string $foreignKey        Name of the foreign key in the current model
     *      @type string $relatedForeignKey Name of the foreign key in the related model
     *      @type bool $dependent           Is the relation dependent on the parent
     * }
     */
    protected $_config = [
        'alias' => '',
        'class' => '',
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
     * Store the alias and class name.
     *
     * @param string $alias
     * @param string $class
     * @param array $config
     */
    public function __construct($alias, $class, array $config = []) {
        parent::__construct($config);

        $this->setAlias($alias);
        $this->setClass($class);
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
    public function getClass() {
        return $this->getConfig('class');
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
    public function getForeignKey() {
        return $this->getConfig('foreignKey');
    }

    /**
     * {@inheritdoc}
     */
    public function getModel() {
        return $this->_model;
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

        $class = $this->getClass();

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
    public function setClass($class) {
        $this->setConfig('class', (string) $class);

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
    public function setForeignKey($key) {
        $this->setConfig('foreignKey', $key);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setModel(Model $model) {
        $this->_model = $model;

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

        return $this;
    }

}