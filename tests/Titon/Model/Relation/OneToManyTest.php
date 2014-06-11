<?php
namespace Titon\Model\Relation;

use Titon\Test\Stub\Model\User;
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

    public function testLinkUnlink() {
        $model1 = new User(['foo' => 'bar']);
        $model2 = new User(['bar' => 'foo']);

        $this->assertEquals([], $this->object->getLinked());

        $this->object->link($model1);

        $this->assertEquals([$model1], $this->object->getLinked());

        $this->object->link($model2);

        $this->assertEquals([$model1, $model2], $this->object->getLinked());

        $this->object->unlink($model1);

        $this->assertEquals([$model2], $this->object->getLinked());

        $this->object->unlink($model2);

        $this->assertEquals([], $this->object->getLinked());
    }

}