<?php
namespace Titon\Model\Relation;

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

    public function testIsDependent() {
        $this->assertFalse($this->object->isDependent());

        // Cannot be changed
        $this->object->setDependent(true);

        $this->assertFalse($this->object->isDependent());
    }

}