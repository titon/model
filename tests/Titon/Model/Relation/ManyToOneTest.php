<?php
namespace Titon\Model\Relation;

use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * @property \Titon\Model\Relation $object
 */
class ManyToOneTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        // User belongs to a country
        $this->object = new ManyToOne('Country', 'Titon\Test\Stub\Model\Country');
        $this->object->setPrimaryClass('Titon\Test\Stub\Model\User');
    }

    public function testGetPrimaryForeignKeyAutoDetect() {
        $this->assertEquals('country_id', $this->object->getPrimaryForeignKey()); // user.country_id
    }

    public function testGetType() {
        $this->assertEquals('manyToOne', $this->object->getType());
    }

    public function testLinkUnlink() {
        $model1 = new User(['foo' => 'bar']);
        $model2 = new User(['bar' => 'foo']);

        $this->assertEquals([], $this->object->getLinked());

        $this->object->link($model1);

        $this->assertEquals([$model1], $this->object->getLinked());

        $this->object->link($model2);

        $this->assertEquals([$model2], $this->object->getLinked());

        $this->object->unlink($model1);

        $this->assertEquals([$model2], $this->object->getLinked());

        $this->object->unlink($model2);

        $this->assertEquals([], $this->object->getLinked());
    }

}