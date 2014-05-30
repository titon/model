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
use Titon\Model\Relation;
use Titon\Utility\Hash;

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
     * {@inheritdoc}
     */
    public function deleteDependents(Event $event, $ids) {
        $pfk = $this->getPrimaryForeignKey();
        $junction = $this->getJunctionRepository();

        foreach ((array) $ids as $id) {
            $junction->deleteMany(function(Query $query) use ($pfk, $id) {
                $query->where($pfk, $id);
            });
        }
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
    public function getResults() {
        if ($this->_results) {
            return $this->_results;
        }

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
            return $this->_results = $junctionResults;
        }

        // Get the related records
        $relatedModel = $this->getRelatedModel();
        $results = $relatedModel->getRepository()
            ->select()
            ->where($relatedModel->getPrimaryKey(), $junctionResults->pluck($this->getRelatedForeignKey()))
            ->bindCallback($this->getConditions())
            ->all();

        if ($results->isEmpty()) {
            return $this->_results = $results;
        }

        // Merge the junction records into the main results
        /** @type \Titon\Model\Model $result */
        foreach ($results as $result) {
            $result->set('junction', $junctionResults->find($result->id(), $this->getRelatedForeignKey()));
        }

        return $this->_results = $results;
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
    public function loadRelations(Event $event, array &$results, $finder) {
        $query = $this->getEagerQuery();

        if (!$query) {
            return;
        }

        $this->_eagerQuery = null;

        $ppk = $this->getPrimaryModel()->getPrimaryKey();
        $pfk = $this->getPrimaryForeignKey();
        $pids = Hash::pluck($results, $ppk);

        // Fetch junction records
        $junctionResults = $this->getJunctionRepository()
            ->select()
            ->where($pfk, $pids)
            ->all();

        if ($junctionResults->isEmpty()) {
            return;
        }

        $rpk = $this->getRelatedModel()->getPrimaryKey();
        $rfk = $this->getRelatedForeignKey();
        $rids = $junctionResults->pluck($rfk);

        // Fetch related records
        $related = $query
            ->where($rpk, $rids)
            ->bindCallback($this->getConditions())
            ->all();

        if ($related->isEmpty()) {
            return;
        }

        $alias = $this->getAlias();

        foreach ($results as $i => $result) {
            $id = $result[$ppk];

            // Get a list of related IDs from the junction records
            $jids = $junctionResults->filter(function($entity) use ($pfk, $id) {
                return ($entity[$pfk] === $id);
            }, false)->pluck($rfk);

            // TODO - merge in junction record
            $results[$i][$alias] = $related->filter(function($entity) use ($rpk, $jids) {
                return in_array($entity[$rpk], $jids);
            }, false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveLinked(Event $event, $ids, $type) {
        if ($type === Query::MULTI_INSERT) {
            return;
        }

        $pfk = $this->getPrimaryForeignKey();
        $rfk = $this->getRelatedForeignKey();
        $junction = $this->getJunctionRepository();
        $links = $this->getLinked();

        foreach ((array) $ids as $id) {
            foreach ($links as $link) {

                // Save the related model in case the data has changed
                if (!$link->save(['validate' => false, 'atomic' => false])) {
                    throw new RelationQueryFailureException(sprintf('Failed to save %s related record(s)', $this->getAlias()));
                }

                // Check if the junction record exists
                $exists = $junction->select()
                    ->where($pfk, $id)
                    ->where($rfk, $link->id())
                    ->count();

                if ($exists) {
                    continue;
                }

                // Save a new junction record
                if (!$junction->create([$pfk => $id, $rfk => $link->id()])) {
                    throw new RelationQueryFailureException(sprintf('Failed to save %s junction record(s)', $this->getAlias()));
                }
            }
        }

        $this->_links = [];
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

}