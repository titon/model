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
use Titon\Model\Model;
use Titon\Model\Relation;
use Titon\Utility\Hash;

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
            ->first();
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
            ->all();

        if ($relatedResults->isEmpty()) {
            return;
        }

        foreach ($results as $i => $result) {
            $results[$i][$alias] = $relatedResults->find($result[$ppk], $rfk);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveLinked(Event $event, $ids, $count) {
        $links = $this->getLinked();
        $rfk = $this->getRelatedForeignKey();

        if (!$ids || !$links) {
            return;
        }

        // Reset the previous records
        $this->query(Query::UPDATE)->where($rfk, $ids)->save([$rfk => null]);

        foreach ((array) $ids as $id) {
            $link = $links[0]->set($rfk, $id);

            if (!$link->save(['validate' => false, 'atomic' => false])) {
                throw new RelationQueryFailureException(sprintf('Failed to save %s related record(s)', $this->getAlias()));
            }
        }

        $this->_links = [];
    }

}