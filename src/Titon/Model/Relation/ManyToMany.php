<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Relation;

use Titon\Db\Repository;
use Titon\Model\Relation;

/**
 * Represents a many-to-many table relationship.
 * Also known as a has and belongs to many.
 *
 * @link http://en.wikipedia.org/wiki/Many-to-many_%28data_model%29
 *
 * @package Titon\Model\Relation
 */
class ManyToMany extends AbstractRelation {

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
     * Junction records should always be deleted.
     *
     * @return bool
     */
    public function isDependent() {
        return true;
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