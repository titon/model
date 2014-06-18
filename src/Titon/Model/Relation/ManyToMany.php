<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

use Titon\Db\Query;
use Titon\Db\Repository;
use Titon\Event\Event;
use Titon\Model\Exception\RelationQueryFailureException;
use Titon\Model\ModelCollection;
use Titon\Model\Relation;

/**
 * Represents a many-to-many table relationship.
 * Also known as a has and belongs to many.
 *
 * @link http://en.wikipedia.org/wiki/Many-to-many_%28data_model%29
 *
 * @package Titon\Model\Relation
 */
class ManyToMany extends Relation {

    /**
     * Configuration.
     *
     * @type array {
     *      @type array $junction  Name of the table, or array of repository settings to use as the junction
     * }
     */
    protected $_config = [
        'junction' => []
    ];

    /**
     * Junction repository instance.
     *
     * @type \Titon\Db\Repository
     */
    protected $_junction;

    /**
     * Delete records found in the junction table, but do not delete records in the foreign table.
     *
     * {@inheritdoc}
     */
    public function deleteDependents(Event $event, $ids, $count) {
        $pfk = $this->getPrimaryForeignKey();

        $this->getJunctionRepository()->deleteMany(function(Query $query) use ($pfk, $ids) {
            $query->where($pfk, $ids);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function fetchRelation() {
        $id = $this->getPrimaryModel()->id();

        if (!$id) {
            return null;
        }

        // Get junction records first
        $junctionResults = $this->getJunctionRepository()
            ->select()
            ->where($this->getPrimaryForeignKey(), $id)
            ->all();

        if ($junctionResults->isEmpty()) {
            return $junctionResults;
        }

        $rfk = $this->getRelatedForeignKey();
        $groupedResults = $junctionResults->groupBy($rfk);

        // Get the related records
        $relatedModel = $this->getRelatedModel();
        $results = $relatedModel->getRepository()
            ->select()
            ->where($relatedModel->getPrimaryKey(), array_keys($groupedResults))
            ->bindCallback($this->getConditions())
            ->all();

        if ($results->isEmpty()) {
            return $results;
        }

        // Merge the junction records into the main results
        foreach ($results as $result) {
            $result['junction'] = $groupedResults[$result->id()][0];
        }

        return $results;
    }

    /**
     * Return the junction table name.
     *
     * @return string
     */
    public function getJunction() {
        return $this->getConfig('junction');
    }

    /**
     * Return the junction repository instance.
     *
     * @return \Titon\Db\Repository
     */
    public function getJunctionRepository() {
        if ($repo = $this->_junction) {
            return $repo;
        }

        $this->setJunctionRepository(new Repository($this->getJunction()));

        return $this->_junction;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryForeignKey() {
        return $this->detectForeignKey('foreignKey', $this->getPrimaryClass());
    }

    /**
     * {@inheritdoc}
     */
    public function getRelatedForeignKey() {
        return $this->detectForeignKey('relatedForeignKey', $this->getRelatedClass());
    }

    /**
     * {@inheritdoc}
     */
    public function getType() {
        return self::MANY_TO_MANY;
    }

    /**
     * {@inheritdoc}
     */
    public function linkRelations(Event $event, $ids, $count) {
        $pfk = $this->getPrimaryForeignKey();
        $rfk = $this->getRelatedForeignKey();
        $links = $this->getLinked();

        if (!$ids || $links->isEmpty()) {
            return;
        }

        // Create a list of records that currently exist
        $junction = $this->getJunctionRepository();
        $newJunctions = [];
        $currentJunctions = $junction->select()
            ->where($pfk, $ids)
            ->lists($pfk, $rfk);

        foreach ((array) $ids as $id) {
            foreach ($links as $link) {

                // Save the related model in case the data has changed
                if (!$link->save(['validate' => false, 'atomic' => false, 'force' => true])) {
                    throw new RelationQueryFailureException(sprintf('Failed to link %s related record(s)', $this->getAlias()));
                }

                $link_id = $link->id();

                // Check if the junction record exists
                if (isset($currentJunctions[$link_id]) && $currentJunctions[$link_id] == $id) {
                    continue;
                }

                $newJunctions[] = [$pfk => $id, $rfk => $link_id];
            }
        }

        // Save all junctions with 1 query
        if (!$junction->createMany($newJunctions)) {
            throw new RelationQueryFailureException(sprintf('Failed to link %s junction record(s)', $this->getAlias()));
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

        // Fetch junction records
        $ppk = $this->getPrimaryModel()->getPrimaryKey();
        $pfk = $this->getPrimaryForeignKey();
        $pids = (new ModelCollection($results))->pluck($ppk);

        $junctionResults = $this->getJunctionRepository()
            ->select()
            ->where($pfk, $pids)
            ->all();

        if ($junctionResults->isEmpty()) {
            return;
        }

        // Fetch related records
        $rpk = $this->getRelatedModel()->getPrimaryKey();
        $rfk = $this->getRelatedForeignKey();
        $rids = $junctionResults->pluck($rfk);

        $relatedResults = $query
            ->where($rpk, $rids)
            ->bindCallback($this->getConditions())
            ->all();

        if ($relatedResults->isEmpty()) {
            return;
        }

        $alias = $this->getAlias();
        $groupedJunctions = $junctionResults->groupBy($pfk);

        foreach ($results as $i => $result) {
            $id = $result[$ppk];

            // Gather the junction records that associate with the current primary modal
            /** @type \Titon\Db\EntityCollection $filteredJunctions */
            $filteredJunctions = $groupedJunctions[$id];
            $filteredIDs = $filteredJunctions->pluck($rfk);

            // Find all related records that match up with the junction IDs
            $relatedMatched = $relatedResults->findMany($filteredIDs, $rpk);

            // Merge the junction record with the matched record
            $relatedMerged = [];

            foreach ($relatedMatched as $entity) {
                $matchedEntity = clone $entity; // Clone entity else appending the junction will update all model references

                foreach ($filteredJunctions as $junctionEntity) {
                    if ($id == $junctionEntity[$pfk] && $matchedEntity[$rpk] == $junctionEntity[$rfk]) {
                        $matchedEntity['junction'] = $junctionEntity;
                    }
                }

                $relatedMerged[] = $matchedEntity;
            };

            $results[$i][$alias] = new ModelCollection($relatedMerged);
        }
    }

    /**
     * Set the junction table name.
     *
     * @param string|array $table
     * @return $this
     */
    public function setJunction($table) {
        if (!is_array($table)) {
            $table = ['table' => $table];
        }

        $this->setConfig('junction', $table);

        return $this;
    }

    /**
     * Set the junction repository.
     *
     * @param \Titon\Db\Repository $repo
     * @return $this
     */
    public function setJunctionRepository(Repository $repo) {
        $this->_junction = $repo;

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

        $rfk = $this->getRelatedForeignKey();
        $ids = $links->pluck($this->getRelatedModel()->getPrimaryKey());

        // Delete the junction records for the unlinked models
        $this->getJunctionRepository()->deleteMany(function(Query $query) use ($rfk, $ids) {
            $query->where($rfk, $ids);
        });

        $links->flush();
    }

}