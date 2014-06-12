<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

use Titon\Db\EntityCollection;
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
     * Child records should not be deleted, but will have the foreign key modified.
     * Use database level `ON DELETE CASCADE` for cascading deletion.
     *
     * {@inheritdoc}
     */
    public function deleteDependents(Event $event, $ids, $count) {
        $rfk = $this->getRelatedForeignKey();

        $this->query(Query::UPDATE)->where($rfk, $ids)->save([$rfk => null]);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchRelation() {
        $id = $this->getPrimaryModel()->id();

        if (!$id) {
            return null;
        }

        return $this->getRelatedModel()
            ->select()
            ->where($this->getRelatedForeignKey(), $id)
            ->bindCallback($this->getConditions())
            ->all();
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

        // Reset query
        $this->_eagerQuery = null;

        $ppk = $this->getPrimaryModel()->getPrimaryKey();
        $rfk = $this->getRelatedForeignKey();
        $alias = $this->getAlias();

        $relatedResults = $query
            ->where($rfk, (new EntityCollection($results))->pluck($ppk))
            ->bindCallback($this->getConditions())
            ->all();

        if ($relatedResults->isEmpty()) {
            return;
        }

        $groupedResults = $relatedResults->groupBy($rfk);

        foreach ($results as $i => $result) {
            $results[$i][$alias] = $groupedResults[$result[$ppk]];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveLinked(Event $event, $ids, $count) {
        $rfk = $this->getRelatedForeignKey();
        $links = $this->getLinked();

        if (!$ids || !$links) {
            return;
        }

        foreach ((array) $ids as $id) {
            foreach ($links as $link) {
                $link->set($rfk, $id);

                if (!$link->save(['validate' => false, 'atomic' => false, 'force' => true])) {
                    throw new RelationQueryFailureException(sprintf('Failed to save %s related record(s)', $this->getAlias()));
                }
            }
        }

        $this->_links = [];
    }

}