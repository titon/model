<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

use Titon\Db\Query;
use Titon\Event\Event;
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
     * Child records should not be deleted.
     * Use database level `ON DELETE CASCADE` for cascading deletion.
     *
     * {@inheritdoc}
     */
    public function deleteDependents(Event $event, $ids) {
        return;
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
    public function loadRelations(Event $event, array &$results, $finder) {
        $query = $this->getEagerQuery();

        if (!$query) {
            return;
        }

        $this->_eagerQuery = null;

        $ppk = $this->getPrimaryModel()->getPrimaryKey();
        $rfk = $this->getRelatedForeignKey();
        $alias = $this->getAlias();

        $relatedResults = $query
            ->where($rfk, Hash::pluck($results, $ppk))
            ->bindCallback($this->getConditions())
            ->all();

        if ($relatedResults->isEmpty()) {
            return;
        }

        foreach ($results as $i => $result) {
            $id = $result[$ppk];

            $results[$i][$alias] = $relatedResults->findMany($id, $rfk);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveLinked(Event $event, $ids, $type) {
        if ($type === Query::MULTI_INSERT) {
            return;
        }

        $rfk = $this->getRelatedForeignKey();
        $links = $this->getLinked();

        foreach ((array) $ids as $id) {
            foreach ($links as $link) {
                $link->set($rfk, $id);

                if (!$link->save(['validate' => false, 'atomic' => false])) {
                    throw new RelationQueryFailureException(sprintf('Failed to save %s related record(s)', $this->getAlias()));
                }
            }
        }

        $this->_links = [];
    }

}