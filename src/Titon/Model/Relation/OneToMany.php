<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

use Titon\Db\Query;
use Titon\Model\Exception\RelationQueryFailureException;
use Titon\Model\Relation;
use Titon\Utility\Hash;

/**
 * Represents a one-to-many table relationship.
 * Also known as a has many.
 *
 * @link http://en.wikipedia.org/wiki/Cardinality_%28data_modeling%29
 *
 * @package Titon\Model\Relation
 */
class OneToMany extends Relation {

    /**
     * {@inheritdoc}
     */
    public function deleteDependents() {
        $id = $this->getPrimaryModel()->id();
        $rfk = $this->getRelatedForeignKey();

        if (!$id || !$this->isDependent()) {
            return 0;
        }

        // TODO - test nested dependents are deleted when using deleteMany()
        return $this->getRelatedModel()->deleteMany(function(Query $query) use ($rfk, $id) {
            $query->where($rfk, $id);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function fetchResults(array $results) {
        if (!$this->_fetch) {
            return $results;
        }

        $ppk = $this->getPrimaryModel()->getPrimaryKey();
        $rfk = $this->getRelatedForeignKey();
        $alias = $this->getAlias();

        $related = $this->getRelatedModel()
            ->select()
            ->where($rfk, Hash::pluck($results, $ppk))
            ->bindCallback($this->getConditions())
            ->all();

        if ($related->isEmpty()) {
            return $results;
        }

        foreach ($results as $i => $result) {
            $id = $result[$ppk];

            $results[$i][$alias] = $related->filter(function($entity) use ($rfk, $id) {
                return ($entity[$rfk] === $id);
            }, false);
        }

        return $results;
    }

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

        $id = $this->getPrimaryModel()->id();

        if (!$id) {
            return null;
        }

        return $this->_results = $this->getRelatedModel()->getRepository()
            ->select()
            ->where($this->getRelatedForeignKey(), $id)
            ->bindCallback($this->getConditions())
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function getType() {
        return self::ONE_TO_MANY;
    }

    /**
     * {@inheritdoc}
     */
    public function saveLinked() {
        $id = $this->getPrimaryModel()->id();

        foreach ($this->getLinked() as $link) {
            $link->set($this->getRelatedForeignKey(), $id);

            if (!$link->save(['validate' => false, 'atomic' => false])) {
                throw new RelationQueryFailureException(sprintf('Failed to save %s related record(s)', $this->getAlias()));
            }
        }

        $this->_links = [];

        return $this;
    }

}