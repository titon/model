<?php
/**
 * @copyright   2010-2014, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Titon\Db\EntityCollection;

/**
 * Houses a collection of Model objects.
 *
 * @package Titon\Model
 * @method \Titon\Model\Model[] value()
 * @method \Titon\Model\Model get($key)
 * @method \Titon\Model\Model[] getIterator()
 * @method \Titon\Model\Model offsetGet($offset)
 */
class ModelCollection extends EntityCollection {

}