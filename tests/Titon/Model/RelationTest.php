<?php
namespace Titon\Model;

use Titon\Db\Query\Expr;
use Titon\Db\Query;
use Titon\Model\Relation\AbstractRelation;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * @property \Titon\Model\Relation\ManyToMany $object
 */
class RelationTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        $this->object = new RelationStub('User', 'Titon\Test\Stub\Model\User');
    }

    public function testBuildForeignKey() {
        $this->assertEquals('user_id', $this->object->buildForeignKey('User'));
        $this->assertEquals('user_id', $this->object->buildForeignKey('Titon\Test\Stub\Model\User'));
        $this->assertEquals('book_genre_id', $this->object->buildForeignKey('Titon\Test\Stub\Model\BookGenre'));
    }

    public function testGetSetAlias() {
        $this->assertEquals('User', $this->object->getAlias());

        $this->object->setAlias('Profile');

        $this->assertEquals('Profile', $this->object->getAlias());
    }

    public function testGetSetConditions() {
        $this->assertEquals(null, $this->object->getConditions());

        $callback = function(){};

        $this->object->setConditions($callback);

        $this->assertSame($callback, $this->object->getConditions());
    }

    public function testGetSetConditionsParams() {
        $query = new Query(Query::SELECT);

        $this->object->setConditions(function(Query $query) {
            $query->where('status', 1);
        });

        $this->assertEquals([], $query->getWhere()->getParams());

        $query->bindCallback($this->object->getConditions(), $this->object);

        $this->assertEquals([new Expr('status', '=', 1)], $query->getWhere()->getParams());
    }

    public function testGetSetPrimaryClass() {
        $this->assertEquals('', $this->object->getPrimaryClass());

        $this->object->setPrimaryClass('Titon\Test\Stub\Model\Profile');

        $this->assertEquals('Titon\Test\Stub\Model\Profile', $this->object->getPrimaryClass());
    }

    public function testGetSetForeignKey() {
        $this->assertEquals(null, $this->object->getPrimaryForeignKey());

        $this->object->setPrimaryForeignKey('user_id');

        $this->assertEquals('user_id', $this->object->getPrimaryForeignKey());
    }

    public function testGetSetModel() {
        $this->assertEquals(null, $this->object->getPrimaryModel());

        $this->object->setPrimaryModel(new User());

        $this->assertInstanceOf('Titon\Model\Model', $this->object->getPrimaryModel());
    }

    public function testGetSetRelatedClass() {
        $this->assertEquals('Titon\Test\Stub\Model\User', $this->object->getRelatedClass());

        $this->object->setRelatedClass('Titon\Test\Stub\Model\Profile');

        $this->assertEquals('Titon\Test\Stub\Model\Profile', $this->object->getRelatedClass());
    }

    public function testGetSetRelatedForeignKey() {
        $this->assertEquals(null, $this->object->getRelatedForeignKey());

        $this->object->setRelatedForeignKey('profile_id');

        $this->assertEquals('profile_id', $this->object->getRelatedForeignKey());
    }

    public function testGetSetRelatedModel() {
        $model = $this->object->getRelatedModel();

        // Auto-instantiate based on class name
        $this->assertInstanceOf('Titon\Test\Stub\Model\User', $model);

        $this->assertSame($model, $this->object->getRelatedModel());
    }

}

class RelationStub extends AbstractRelation {
    public function getType() { return ''; }
}