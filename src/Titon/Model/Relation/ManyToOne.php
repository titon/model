<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

use Titon\Event\Event;
use Titon\Model\Exception\RelationQueryFailureException;
use Titon\Model\Model;
use Titon\Model\ModelCollection;
use Titon\Model\Relation;

/**
 * Represents a many-to-one table relationship.
 * Also known as a belongs to.
 *
 * @link http://en.wikipedia.org/wiki/Cardinality_%28data_modeling%29
 *
 * @package Titon\Model\Relation
 */
class ManyToOne extends Relation {

    /**
     * Parent belongs to relations should not be deleted when children are.
     *
     * {@inheritdoc}
     */
    public function deleteDependents(Event $event, $ids, $count) {
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchRelation() {
        $foreignID = $this->getPrimaryModel()->get($this->getPrimaryForeignKey());

        if (!$foreignID) {
            return null;
        }

        $relatedModel = $this->getRelatedModel();

        return $relatedModel
            ->select()
            ->where($relatedModel->getPrimaryKey(), $foreignID)
            ->bindCallback($this->getConditions())
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryForeignKey() {
        return $this->detectForeignKey('foreignKey', $this->getRelatedClass());
    }

    /**
     * {@inheritdoc}
     */
    public function getType() {
        return self::MANY_TO_ONE;
    }

    /**
     * Only one record at a time can be linked in a belongs to relation.
     * Also include the ID from the foreign model as an attribute on the primary model.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function link(Model $model) {
        $this->getLinked()->flush()->append($model);

        $this->getPrimaryModel()->set($this->getPrimaryForeignKey(), $model->id());

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function linkRelations(Event $event, $ids, $count) {
        $links = $this->getLinked();

        if ($links->isEmpty()) {
            return;
        }

        foreach ($links as $link) {
            if (!$link->save(['validate' => false, 'atomic' => false, 'force' => true])) {
                throw new RelationQueryFailureException(sprintf('Failed to link %s related record(s)', $this->getAlias()));
            }
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

        $rpk = $this->getRelatedModel()->getPrimaryKey();
        $pfk = $this->getPrimaryForeignKey();
        $alias = $this->getAlias();

        $relatedResults = $query
            ->where($rpk, (new ModelCollection($results))->pluck($pfk))
            ->bindCallback($this->getConditions())
            ->all();

        if ($relatedResults->isEmpty()) {
            return;
        }

        foreach ($results as $i => $result) {
            $results[$i][$alias] = $relatedResults->find($result[$pfk], $rpk);
        }
    }

    /**
     * Only one record at a time can be unlinked in a belongs to relation.
     * Also reset the foreign key attribute in the primary model.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function unlink(Model $model) {
        $this->getUnlinked()->flush()->append($model);

        $this->getPrimaryModel()->set($this->getPrimaryForeignKey(), null);

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

        foreach ($links as $link) {
            if (!$link->save(['validate' => false, 'atomic' => false, 'force' => true])) {
                throw new RelationQueryFailureException(sprintf('Failed to unlink %s related record(s)', $this->getAlias()));
            }
        }

        $links->flush();
    }

}