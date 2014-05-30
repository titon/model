<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Behavior;

use Titon\Common\Traits\Cacheable;
use Titon\Db\Behavior\AbstractBehavior;
use Titon\Event\Event;
use Titon\Model\ModelAware;
use Titon\Model\Relation;
use Titon\Model\Relation\ManyToMany;
use Titon\Model\Relation\ManyToOne;
use \Closure;

/**
 * The CounterBehavior provides a way for many-to-one|many relations to track a count of how many related records exist.
 * Each time a record is created, updated or deleted, the count will be updated in the related record.
 *
 * @package Titon\Db\Behavior
 */
class CounterBehavior extends AbstractBehavior {
    use Cacheable, ModelAware;

    /**
     * List of defined counter settings.
     *
     * @type array
     */
    protected $_counters = [];

    /**
     * Add a counter for a relation.
     *
     * @param string $alias
     * @param string $field
     * @param \Closure $scope
     * @return $this
     */
    public function track($alias, $field, Closure $scope = null) {
        $this->_counters[$alias] = [
            'field' => $field,
            'scope' => $scope
        ];

        return $this;
    }

    /**
     * Return all the counter configurations.
     *
     * @return array
     */
    public function getCounters() {
        return $this->_counters;
    }

    /**
     * Fetch records about to be deleted since they do not exist in postDelete().
     *
     * @param \Titon\Event\Event $event
     * @param int|int[] $id
     * @return mixed
     */
    public function preDelete(Event $event, $id) {
        $model = $this->getModel();

        foreach ($this->getCounters() as $alias => $counter) {
            $relation = $model->getRelation($alias);

            foreach ((array) $id as $value) {
                switch ($relation->getType()) {
                    case Relation::MANY_TO_MANY:
                        /** @type \Titon\Model\Relation\ManyToMany $relation */
                        $results = $relation->getJunctionRepository()
                            ->select()
                            ->where($relation->getPrimaryForeignKey(), $value)
                            ->bindCallback($counter['scope'])
                            ->all();

                        $this->setCache(['Junction', $alias, $value], $results);
                    break;
                    case Relation::MANY_TO_ONE:
                        $this->setCache(['Record', $alias, $value], $model->getRepository()->read($value));
                    break;
                }
            }
        }

        return true;
    }

    /**
     * Sync counters after a record is deleted.
     *
     * @param \Titon\Event\Event $event
     * @param int|int[] $id
     */
    public function postDelete(Event $event, $id) {
        $this->syncCounters($id);
    }

    /**
     * Sync counters after a record is saved.
     *
     * @param \Titon\Event\Event $event
     * @param int|int[] $id
     * @param bool $created
     */
    public function postSave(Event $event, $id, $created = false) {
        $this->syncCounters($id);
    }

    /**
     * {@inheritdoc}
     */
    public function registerEvents() {
        return [
            'db.preDelete' => 'preDelete',
            'db.postDelete' => 'postDelete',
            'db.postSave' => 'postSave'
        ];
    }

    /**
     * Sync the count fields in the related table.
     * Loop over each counter, and loop over each modified record ID.
     * Determine the count while applying scope and update the relation record.
     *
     * @param int|int[] $ids
     * @return $this
     */
    public function syncCounters($ids) {
        foreach ($this->getCounters() as $alias => $counter) {
            $relation = $this->getModel()->getRelation($alias);

            switch ($relation->getType()) {
                case Relation::MANY_TO_MANY:
                    $this->_syncManyToMany($relation, $ids, $counter);
                break;
                case Relation::MANY_TO_ONE:
                    $this->_syncManyToOne($relation, $ids, $counter);
                break;
            }
        }

        // Reset cache for this sync
        $this->flushCache();

        return $this;
    }

    /**
     * Sync many-to-many counters with the following process:
     *
     *     - Loop through the current table IDs
     *     - Fetch all junction table records where foreign key matches current ID
     *     - Loop over junction records and grab the related foreign key value
     *     - Count all junction table records where related foreign key matches the previous related value
     *     - Update the related table with the junction count
     *
     * Using this example setup:
     *
     *     - Entry (jfk:entry_id) has and belongs to many Tag (jfk:tag_id) (entry_count)
     *
     * @param \Titon\Model\Relation\ManyToMany $relation
     * @param int|int[] $ids
     * @param array $counter
     */
    protected function _syncManyToMany(ManyToMany $relation, $ids, array $counter) {
        $alias = $relation->getAlias();
        $fk = $relation->getPrimaryForeignKey();
        $rfk = $relation->getRelatedForeignKey();
        $junctionRepo = $relation->getJunctionRepository();
        $relatedRepo = $relation->getRelatedModel()->getRepository();

        foreach ((array) $ids as $id) {
            $results = $this->getCache(['Junction', $alias, $id]);

            if (!$results) {
                $results = $junctionRepo->select()
                    ->where($fk, $id)
                    ->bindCallback($counter['scope'])
                    ->all();
            }

            // Loop over each junction record and update the related record
            foreach ($results as $result) {
                $foreign_id = $result[$rfk];
                $cacheKey = [$alias, $fk, $foreign_id];

                // Skip if this has already been counted
                if ($this->hasCache($cacheKey)) {
                    continue;
                }

                // Get a count of all junction records
                $count = $junctionRepo->select()
                    ->where($rfk, $foreign_id)
                    ->count();

                // Update the related table's count field
                $relatedRepo->update($foreign_id, [
                    $counter['field'] => $count
                ]);

                $this->setCache($cacheKey, true);
            }
        }
    }

    /**
     * Sync many-to-one counters with the following process:
     *
     *     - Loop through the current table IDs
     *     - Fetch the current table record that matches the ID
     *     - Count the current table records where foreign key matches the foreign key value from the previous record
     *     - Update the related table with the count
     *
     * Using this example setup:
     *
     *     - Post (fk:topic_id) belongs to Topic (post_count)
     *
     * @param \Titon\Model\Relation\ManyToOne $relation
     * @param int|int[] $ids
     * @param array $counter
     */
    protected function _syncManyToOne(ManyToOne $relation, $ids, array $counter) {
        $repo = $this->getModel()->getRepository();
        $alias = $relation->getAlias();
        $fk = $relation->getPrimaryForeignKey();
        $relatedRepo = $relation->getRelatedModel()->getRepository();

        foreach ((array) $ids as $id) {
            $result = $this->getCache(['Record', $alias, $id]) ?: $repo->read($id);
            $foreign_id = $result[$fk];
            $cacheKey = [$alias, $fk, $foreign_id];

            // Skip if this has already been counted
            if ($this->hasCache($cacheKey)) {
                continue;
            }

            // Get a count of all current records
            $count = $repo->select()
                ->where($fk, $foreign_id)
                ->bindCallback($counter['scope'])
                ->count();

            // Update the related table's count field
            $relatedRepo->update($foreign_id, [
                $counter['field'] => $count
            ]);

            $this->setCache($cacheKey, true);
        }
    }

}