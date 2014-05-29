<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

use Titon\Db\Query;
use Titon\Model\Exception\RelationQueryFailureException;
use Titon\Model\Model;
use Titon\Model\Relation;

/**
 * Represents a one-to-one table relationship.
 * Also known as a has one.
 *
 * @link http://en.wikipedia.org/wiki/Cardinality_%28data_modeling%29
 *
 * @package Titon\Model\Relation
 */
class OneToOne extends Relation {

    /**
     * {@inheritdoc}
     */
    public function deleteDependents() {
        if (!$this->isDependent()) {
            return 0;
        }

        if ($model = $this->getResults()) {
            return $model->delete();
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getRelatedForeignKey() {
        return $this->detectForeignKey('relatedForeignKey', $this->getPrimaryClass());
    }

    /**
     * {@inheritdoc}
     *
     * @return \Titon\Model\Model
     */
    public function getResults() {
        if ($this->_results) {
            return $this->_results;
        }

        $id = $this->getPrimaryModel()->id();

        if (!$id) {
            return null;
        }

        return $this->_results = $this->getRelatedModel()->getRepository()
            ->select()
            ->where($this->getRelatedForeignKey(), $id)
            ->bindCallback($this->getConditions())
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getType() {
        return self::ONE_TO_ONE;
    }

    /**
     * Only one record at a time can be linked in a has one relation.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function link(Model $model) {
        $this->_links = [$model];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function saveLinked() {
        $links = $this->getLinked();

        if (!$links) {
            return $this;
        }

        $id = $this->getPrimaryModel()->id();
        $rfk = $this->getRelatedForeignKey();

        // Reset the previous record
        $this->getRelatedModel()->updateMany([$rfk => null], function(Query $query) use ($rfk, $id) {
            $query->where($rfk, $id);
        });

        // Save the new record
        $link = $links[0];
        $link->set($this->getRelatedForeignKey(), $id);

        if (!$link->save(['validate' => false, 'atomic' => false])) {
            throw new RelationQueryFailureException(sprintf('Failed to save %s related record(s)', $this->getAlias()));
        }

        $this->_links = [];

        return $this;
    }

}