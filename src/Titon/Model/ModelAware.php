<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

/**
 * Permits a class to interact with a model.
 *
 * @package Titon\Model
 */
trait ModelAware {

    /**
     * Model object instance.
     *
     * @type \Titon\Model\Model
     */
    protected $_model;

    /**
     * Return the model.
     *
     * @return \Titon\Model\Model
     */
    public function getModel() {
        return $this->_model;
    }

    /**
     * Set the model.
     *
     * @param \Titon\Model\Model $model
     * @return $this
     */
    public function setModel(Model $model) {
        $this->_model = $model;

        return $this;
    }

}