<?php
namespace Titon\Model\Relation;

use Titon\Test\TestCase;

/**
 * @property \Titon\Model\Relation $object
 */
class OneToManyTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        // User has many posts
        $this->object = new OneToMany('Post', 'Titon\Test\Stub\Model\Post');
        $this->object->setPrimaryClass('Titon\Test\Stub\Model\User');
    }

    public function testGetRelatedForeignKeyAutoDetect() {
        $this->assertEquals('user_id', $this->object->getRelatedForeignKey()); // post.user_id
    }

    public function testGetType() {
        $this->assertEquals('oneToMany', $this->object->getType());
    }

    public function testIsDependent() {
        $this->assertTrue($this->object->isDependent());

        $this->object->setDependent(false);

        $this->assertFalse($this->object->isDependent());
    }

}