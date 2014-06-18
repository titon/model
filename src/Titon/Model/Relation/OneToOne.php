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
use Titon\Model\Model;
use Titon\Model\ModelCollection;
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
        $this->getLinked()->flush()->append($model);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function linkRelations(Event $event, $id, $count) {
        $links = $this->getLinked();
        $rfk = $this->getRelatedForeignKey();

        if (!$id || $links->isEmpty()) {
            return;
        }

        // Reset the previous records
        $this->query(Query::UPDATE)->where($rfk, $id)->save([$rfk => null]);

        // Save the current record
        $link = $links[0]->set($rfk, $id);

        if (!$link->save(['validate' => false, 'atomic' => false, 'force' => true])) {
            throw new RelationQueryFailureException(sprintf('Failed to link %s related record(s)', $this->getAlias()));
        }

        $links->flush();
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
            ->where($rfk, (new ModelCollection($results))->pluck($ppk))
            ->all();

        if ($relatedResults->isEmpty()) {
            return;
        }

        foreach ($results as $i => $result) {
            $results[$i][$alias] = $relatedResults->find($result[$ppk], $rfk);
        }
    }

    /**
     * Only one record at a time can be unlinked in a has one relation.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function unlink(Model $model) {
        $this->getUnlinked()->flush()->append($model);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function unlinkRelations(Event $event, $ids, $count) {
        $links = $this->getUnlinked();

        if ($links->isEmpty()) {
            return;
        }

        $link = $links[0]->set($this->getRelatedForeignKey(), null);

        if (!$link->save(['validate' => false, 'atomic' => false, 'force' => true])) {
            throw new RelationQueryFailureException(sprintf('Failed to unlink %s related record(s)', $this->getAlias()));
        }

        $links->flush();
    }

}