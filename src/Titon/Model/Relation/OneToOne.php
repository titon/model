<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

/**
 * Represents a one-to-one table relationship.
 * Also known as a has one.
 *
 * @link http://en.wikipedia.org/wiki/Cardinality_%28data_modeling%29
 *
 * @package Titon\Model\Relation
 */
class OneToOne extends AbstractRelation {

    /**
     * {@inheritdoc}
     */
    public function getRelatedForeignKey() {
        return $this->detectForeignKey('relatedForeignKey', $this->getPrimaryClass());
    }

    /**
     * {@inheritdoc}
     */
    public function getResults() {
        if ($this->_results) {
            return $this->_results;
        }

        $model = $this->getPrimaryModel();
        $foreignKey = $model->get($model->getPrimaryKey());

        if (!$foreignKey) {
            return null;
        }

        return $this->_results = $this->getRelatedModel()->getRepository()
            ->select()
            ->where($this->getRelatedForeignKey(), $foreignKey)
            ->bindCallback($this->getConditions())
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getType() {
        return self::ONE_TO_ONE;
    }

}