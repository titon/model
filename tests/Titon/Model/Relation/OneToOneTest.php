<?php
namespace Titon\Model\Relation;

use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * @property \Titon\Model\Relation $object
 */
class OneToOneTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        // User has one profile
        $this->object = new OneToOne('Profile', 'Titon\Test\Stub\Model\Profile');
        $this->object->setPrimaryClass('Titon\Test\Stub\Model\User');
    }

    public function testGetRelatedForeignKeyAutoDetect() {
        $this->assertEquals('user_id', $this->object->getRelatedForeignKey()); // profile.user_id
    }

    public function testGetType() {
        $this->assertEquals('oneToOne', $this->object->getType());
    }

}