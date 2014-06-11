<?php
namespace Titon\Model\Relation;

use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * @property \Titon\Model\Relation\ManyToMany $object
 */
class ManyToManyTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        // Book belongs to many genre
        $this->object = new ManyToMany('Genre', 'Titon\Test\Stub\Model\Genre');
        $this->object->setPrimaryClass('Titon\Test\Stub\Model\Book');
    }

    public function testGetPrimaryForeignKeyAutoDetect() {
        $this->assertEquals('book_id', $this->object->getPrimaryForeignKey()); // junction.book_id
    }

    public function testGetRelatedForeignKeyAutoDetect() {
        $this->assertEquals('genre_id', $this->object->getRelatedForeignKey()); // junction.book_id
    }

    public function testGetType() {
        $this->assertEquals('manyToMany', $this->object->getType());
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