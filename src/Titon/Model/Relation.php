<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Closure;

/**
 * Represents a relation of one table to another.
 *
 * @package Titon\Model
 */
interface Relation {

    const ONE_TO_ONE = 'oneToOne'; // Has one
    const ONE_TO_MANY = 'oneToMany'; // Has many
    const MANY_TO_ONE = 'manyToOne'; // Belongs to
    const MANY_TO_MANY = 'manyToMany'; // Has and belongs to many

    /**
     * Return the relation alias name.
     *
     * @return string
     */
    public function getAlias();

    /**
     * Return the query conditions.
     *
     * @return \Closure
     */
    public function getConditions();

    /**
     * Return the primary model class name.
     *
     * @return string
     */
    public function getPrimaryClass();

    /**
     * Return the name of the foreign key for the primary model.
     *
     * @return string
     */
    public function getPrimaryForeignKey();

    /**
     * Return a primary model object.
     *
     * @return \Titon\Model\Model
     */
    public function getPrimaryModel();

    /**
     * Return the related model class name.
     *
     * @return string
     */
    public function getRelatedClass();

    /**
     * Return the name of the related foreign key.
     *
     * @return string
     */
    public function getRelatedForeignKey();

    /**
     * Return a related model object.
     *
     * @return \Titon\Model\Model
     */
    public function getRelatedModel();

    /**
     * Return the type of relation.
     *
     * @return string
     */
    public function getType();

    /**
     * Return true if the relation is dependent to the parent.
     *
     * @return bool
     */
    public function isDependent();

    /**
     * Set the alias name.
     *
     * @param string $alias
     * @return $this
     */
    public function setAlias($alias);

    /**
     * Set the query conditions for this relation.
     *
     * @param \Closure $callback
     * @return $this
     */
    public function setConditions(Closure $callback);

    /**
     * Set relation dependency.
     *
     * @param bool $state
     * @return $this
     */
    public function setDependent($state);

    /**
     * Set the primary model class name.
     *
     * @param string $class
     * @return $this
     */
    public function setPrimaryClass($class);

    /**
     * Set the foreign key for the primary model.
     *
     * @param string $key
     * @return $this
     */
    public function setPrimaryForeignKey($key);

    /**
     * Set the primary model object.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function setPrimaryModel(Model $model);

    /**
     * Set the related model class name.
     *
     * @param string $class
     * @return $this
     */
    public function setRelatedClass($class);

    /**
     * Set the foreign key for the related table.
     *
     * @param string $key
     * @return $this
     */
    public function setRelatedForeignKey($key);

    /**
     * Set the related model object.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function setRelatedModel(Model $model);

}