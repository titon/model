<?php
namespace Titon\Model;

use Titon\Db\Behavior\TimestampBehavior;
use Titon\Model\Relation\ManyToMany;
use Titon\Model\Relation\ManyToOne;
use Titon\Model\Relation\OneToMany;
use Titon\Model\Relation\OneToOne;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;
use Titon\Utility\Validator;

/**
 * @property \Titon\Model\Model $object
 */
class ModelTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        $this->object = new User();
    }

    public function testAddBehavior() {
        $this->assertFalse($this->object->hasBehavior('Timestamp'));

        $this->object->addBehavior(new TimestampBehavior());

        $this->assertTrue($this->object->hasBehavior('Timestamp'));
    }

    public function testAddRelation() {
        $this->assertFalse($this->object->hasRelation('Post'));

        $this->object->addRelation(new OneToMany('Post', 'Titon\Test\Stub\Model\Post'));

        $this->assertTrue($this->object->hasRelation('Post'));
        $this->assertTrue($this->object->hasRelations());
    }

    public function testBelongsTo() {
        $conditions = function() {};

        $this->object->belongsTo('Country', 'Titon\Test\Stub\Model\Post', 'country_fk', $conditions);

        $relation = $this->object->getRelation('Country');

        $this->assertEquals('Country', $relation->getAlias());
        $this->assertEquals('Titon\Test\Stub\Model\Post', $relation->getRelatedClass());
        $this->assertEquals('country_fk', $relation->getPrimaryForeignKey());
        $this->assertEquals(Relation::MANY_TO_ONE, $relation->getType());
        $this->assertSame($conditions, $relation->getConditions());
    }

    public function testBelongsToMany() {
        $conditions = function() {};

        $this->object->belongsToMany('Roles', 'Titon\Test\Stub\Model\Role', 'user_roles', 'user_fk', 'role_fk', $conditions);

        /** @type \Titon\Model\Relation\ManyToMany $relation */
        $relation = $this->object->getRelation('Roles');

        $this->assertEquals('Roles', $relation->getAlias());
        $this->assertEquals('Titon\Test\Stub\Model\Role', $relation->getRelatedClass());
        $this->assertEquals('user_fk', $relation->getPrimaryForeignKey());
        $this->assertEquals('role_fk', $relation->getRelatedForeignKey());
        $this->assertEquals(['table' => 'user_roles'], $relation->getJunction());
        $this->assertEquals(Relation::MANY_TO_MANY, $relation->getType());
        $this->assertSame($conditions, $relation->getConditions());
    }

    public function testExists() {
        $this->loadFixtures('Users');

        $this->assertTrue(User::find(1)->exists());
        $this->assertFalse(User::find(10)->exists());
    }

    public function testFill() {
        $this->object->fill(['country_id' => 1, 'username' => 'miles', 'firstName' => 'Miles', 'lastName' => 'Johnson', 'password' => '1Z5895jf72yL77h', 'email' => 'miles@email.com', 'age' => 25, 'created' => '1988-02-26 21:22:34']);

        $this->assertEquals([
            'username' => 'miles',
            'firstName' => 'Miles'
        ], $this->object->toArray());

        $this->object->fill(['username' => 'batman']);

        $this->assertEquals(['username' => 'batman'], $this->object->toArray());
    }

    /**
     * @expectedException \Titon\Model\Exception\MassAssignmentException
     */
    public function testFillFullyGuarded() {
        $profile = new Profile();
        $profile->fill(['user_id' => 4, 'lastLogin' => '2012-02-03 21:22:34', 'currentLogin' => '2013-06-06 19:11:03']);
    }

    public function testGet() {
        $this->loadFixtures('Users');

        $user = User::find(1);

        $this->assertEquals('Miles', $user->firstName);
        $this->assertEquals('Miles', $user->get('firstName'));
    }

    public function testGetAccessor() {
        $this->loadFixtures('Users');

        $user = User::find(1);

        $this->assertEquals([
            'id' => 1,
            'country_id' => 1,
            'username' => 'miles',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null
        ], $user->toArray());

        $this->assertEquals('Miles Johnson', $user->fullName);
    }

    public function testGetDisplayField() {
        $this->assertEquals(['title', 'name', 'id'], $this->object->getDisplayField());
    }

    public function testGetRepository() {
        $repo = $this->object->getRepository();

        $this->assertEquals('default', $repo->getConfig('connection'));
        $this->assertEquals('users', $repo->getConfig('table'));
        $this->assertEquals('', $repo->getConfig('prefix'));
        $this->assertEquals('id', $repo->getConfig('primaryKey'));
        $this->assertEquals(['title', 'name', 'id'], $repo->getConfig('displayField'));
        $this->assertEquals('Titon\Test\Stub\Model\User', $repo->getConfig('entity'));
    }

    public function testGetRelation() {
        $this->assertInstanceOf('Titon\Model\Relation\ManyToOne', $this->object->getRelation('Country'));
    }

    /**
     * @expectedException \Titon\Model\Exception\MissingRelationException
     */
    public function testGetRelationThrowsError() {
        $this->object->getRelation('Posts');
    }

    public function testGetRelations() {
        $this->assertEquals([
            'Country' => (new ManyToOne('Country', 'Titon\Test\Stub\Model\Country'))->setPrimaryForeignKey(null)->setPrimaryModel($this->object),
            'Profile' => (new OneToOne('Profile', 'Titon\Test\Stub\Model\Profile'))->setRelatedForeignKey(null)->setPrimaryModel($this->object)
        ], $this->object->getRelations());
    }

    public function testGetRelationsFiltered() {
        $this->assertEquals([
            'Country' => (new ManyToOne('Country', 'Titon\Test\Stub\Model\Country'))->setPrimaryForeignKey(null)->setPrimaryModel($this->object)
        ], $this->object->getRelations(Relation::MANY_TO_ONE));
    }

    public function testGetTable() {
        $this->assertEquals('users', $this->object->getTable());
    }

    public function testGetTablePrefix() {
        $this->assertEquals('', $this->object->getTablePrefix());
    }

    public function testGetValidator() {
        $validator = new Validator();
        $validator->addField('username', 'username', [
            'between' => [5, 25],
            'alphaNumeric'
        ]);
        $validator->addField('firstName', 'firstName', ['alpha']);
        $validator->addField('lastName', 'lastName', ['numeric']);

        $this->assertEquals($validator, $this->object->getValidator());
    }

    public function testHasAccessor() {
        $this->assertEquals(null, $this->object->hasAccessor('email'));
        $this->assertEquals('getFullnameAttribute', $this->object->hasAccessor('fullName'));
        $this->assertEquals('getFullNameAttribute', $this->object->hasAccessor('full_name'));
    }

    public function testHasMutator() {
        $this->assertEquals(null, $this->object->hasMutator('email'));
        $this->assertEquals('setFullnameAttribute', $this->object->hasMutator('fullName'));
        $this->assertEquals('setFullNameAttribute', $this->object->hasMutator('full_name'));
    }

    public function testHasOne() {
        $conditions = function() {};

        $this->object->hasOne('Profile', 'Titon\Test\Stub\Model\Profile', 'user_fk', $conditions);

        $relation = $this->object->getRelation('Profile');

        $this->assertEquals('Profile', $relation->getAlias());
        $this->assertEquals('Titon\Test\Stub\Model\Profile', $relation->getRelatedClass());
        $this->assertEquals('user_fk', $relation->getRelatedForeignKey());
        $this->assertEquals(Relation::ONE_TO_ONE, $relation->getType());
        $this->assertSame($conditions, $relation->getConditions());
    }

    public function testHasMany() {
        $conditions = function() {};

        $this->object->hasMany('Posts', 'Titon\Test\Stub\Model\Post', 'user_fk', $conditions);

        $relation = $this->object->getRelation('Posts');

        $this->assertEquals('Posts', $relation->getAlias());
        $this->assertEquals('Titon\Test\Stub\Model\Post', $relation->getRelatedClass());
        $this->assertEquals('user_fk', $relation->getRelatedForeignKey());
        $this->assertEquals(Relation::ONE_TO_MANY, $relation->getType());
        $this->assertSame($conditions, $relation->getConditions());
    }

    public function testId() {
        $this->assertEquals(null, $this->object->id());

        $this->object->id = 123;

        $this->assertEquals(123, $this->object->id());
    }

    public function testIsFillable() {
        $profile = new Profile();

        $this->assertTrue($this->object->isFillable('username'));
        $this->assertFalse($this->object->isFillable('password'));
        $this->assertTrue($profile->isFillable('lastLogin')); // All allowed
    }

    public function testIsGuarded() {
        $profile = new Profile();

        $this->assertFalse($this->object->isGuarded('username'));
        $this->assertTrue($this->object->isGuarded('password'));
        $this->assertTrue($profile->isGuarded('lastLogin')); // All denied
    }

    public function testIsFullyGuarded() {
        $profile = new Profile();

        $this->assertFalse($this->object->isFullyGuarded());
        $this->assertTrue($profile->isFullyGuarded());
    }

    public function testLoadRelationships() {
        $conditions = function() {};
        $model = new ModelRelationStub(
            [
                'Profile' => 'Titon\Test\Stub\Model\Profile'
            ],
            [
                'Topics' => 'Titon\Test\Stub\Model\Topic',
                'Posts' => ['model' => 'Titon\Test\Stub\Model\Post', 'relatedForeignKey' => 'user_fk']
            ],
            [
                'Country' => ['model' => 'Titon\Test\Stub\Model\Country', 'conditions' => $conditions]
            ],
            [
                'Roles' => ['model' => 'Titon\Test\Stub\Model\Role', 'junction' => 'user_roles', 'foreignKey' => 'user_fk']
            ]
        );

        $relations = $model->getRelations();

        $this->assertEquals([
            'Country' => (new ManyToOne('Country', 'Titon\Test\Stub\Model\Country'))->setPrimaryModel($model)->setPrimaryForeignKey(null)->setConditions($conditions),
            'Roles' => (new ManyToMany('Roles', 'Titon\Test\Stub\Model\Role'))->setPrimaryModel($model)->setPrimaryForeignKey('user_fk')->setRelatedForeignKey(null)->setJunction('user_roles'),
            'Profile' => (new OneToOne('Profile', 'Titon\Test\Stub\Model\Profile'))->setPrimaryModel($model)->setRelatedForeignKey(null),
            'Topics' => (new OneToMany('Topics', 'Titon\Test\Stub\Model\Topic'))->setPrimaryModel($model)->setRelatedForeignKey(null),
            'Posts' => (new OneToMany('Posts', 'Titon\Test\Stub\Model\Post'))->setPrimaryModel($model)->setRelatedForeignKey('user_fk'),
        ], $relations);
    }

    public function testSet() {
        $user = new User();
        $user->email = 'miles@email.com';

        $this->assertEquals('miles@email.com', $user->get('email'));
    }

    public function testSetMutator() {
        $user = new User();
        $user->fullName = 'Miles Johnson';

        $this->assertEquals([
            'firstName' => 'Miles',
            'lastName' => 'Johnson'
        ], $user->toArray());
    }

    public function testSetValidator() {
        $validator = new Validator();

        $this->object->setValidator($validator);

        $this->assertSame($validator, $this->object->getValidator());
    }

    public function testValidate() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->username = 'foo'; // needs to be 5

        $this->assertEquals(0, $user->save());
        $this->assertEquals([
            'username' => 'Must be between 5 and 25 chars'
        ], $user->getErrors());

        $user->username = 'foobar'; // good!

        $this->assertNotEquals(0, $user->save());
        $this->assertEquals([], $user->getErrors());

        $user->username = 'bar'; // ignore validation

        $this->assertNotEquals(0, $user->save(['validate' => false]));
        $this->assertEquals([], $user->getErrors());

        // Now with multiple fields
        $user->username = 'foo';
        $user->firstName = 123;
        $user->lastName = 'abc';

        $this->assertEquals(0, $user->save());
        $this->assertEquals([
            'username' => 'Must be between 5 and 25 chars',
            'firstName' => 'Must be alphabetical',
            'lastName' => 'Must be a number'
        ], $user->getErrors());
    }

}

// Stub model for testing loadRelationships()
class ModelRelationStub extends Model {
    public function __construct(array $hasOne, array $hasMany, array $belongsTo, array $belongsToMany) {
        $this->hasOne = $hasOne;
        $this->hasMany = $hasMany;
        $this->belongsTo = $belongsTo;
        $this->belongsToMany = $belongsToMany;
        $this->initialize();
    }
}