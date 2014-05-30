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

        return $this->_results = $this->getRelatedModel()
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
    public function loadRelations(Event $event, array &$results, $finder) {
        $query = $this->getEagerQuery();

        if (!$query) {
            return;
        }

        $this->_eagerQuery = null;

        $ppk = $this->getPrimaryModel()->getPrimaryKey();
        $rfk = $this->getRelatedForeignKey();
        $alias = $this->getAlias();

        $related = $query
            ->where($rfk, Hash::pluck($results, $ppk))
            ->all();

        if ($related->isEmpty()) {
            return;
        }

        foreach ($results as $i => $result) {
            $results[$i][$alias] = $related->find($result[$ppk], $rfk);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveLinked(Event $event, $ids, $type) {
        $links = $this->getLinked();
        $rfk = $this->getRelatedForeignKey();

        if (!$links || $type === Query::MULTI_INSERT) {
            return;
        }

        foreach ((array) $ids as $id) {

            // Reset the previous record
            $this->getRelatedModel()->updateMany([$rfk => null], function(Query $query) use ($rfk, $id) {
                $query->where($rfk, $id);
            });

            // Save the new record
            $link = $links[0]->set($rfk, $id);

            if (!$link->save(['validate' => false, 'atomic' => false])) {
                throw new RelationQueryFailureException(sprintf('Failed to save %s related record(s)', $this->getAlias()));
            }
        }

        $this->_links = [];
    }

}