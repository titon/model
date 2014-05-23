<?php
namespace Titon\Model\Relation;

use Titon\Test\TestCase;

/**
 * @property \Titon\Model\Relation\ManyToMany $object
 */
class ManyToManyTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        $this->object = new ManyToMany('Alias', 'Namespace\Model');
    }

    public function testGetType() {
        $this->assertEquals('manyToMany', $this->object->getType());
    }

    public function testIsDependent() {
        $this->assertTrue($this->object->isDependent());

        // Cannot be changed
        $this->object->setDependent(false);

        $this->assertTrue($this->object->isDependent());
    }

    public function testGetSetJunction() {
        $this->assertEquals([], $this->object->getJunction());

        $this->object->setJunction('lookup_table');

        $this->assertEquals(['table' => 'lookup_table'], $this->object->getJunction());

        $this->object->setJunction([
            'table' => 'lookup_table',
            'primaryKey' => 'uuid'
        ]);

        $this->assertEquals([
            'table' => 'lookup_table',
            'primaryKey' => 'uuid'
        ], $this->object->getJunction());
    }

    public function testGetSetJunctionRepository() {
        $this->object->setJunction('lookup_table');

        $repo = $this->object->getJunctionRepository();

        $this->assertInstanceOf('Titon\Db\Repository', $repo);
        $this->assertEquals('lookup_table', $repo->getTable());
        $this->assertSame($repo, $this->object->getJunctionRepository());
    }

}