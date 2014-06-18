<?php
namespace Titon\Model;

use Titon\Db\Behavior\TimestampBehavior;
use Titon\Db\EntityCollection;
use Titon\Db\Query;
use Titon\Model\Relation\ManyToMany;
use Titon\Model\Relation\ManyToOne;
use Titon\Model\Relation\OneToMany;
use Titon\Model\Relation\OneToOne;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Genre;
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

    public function testClone() {
        $user1 = new User(['foo' => 'bar']);
        $user2 = clone $user1;

        $this->assertNotSame($user1, $user2);
    }

    public function testCount() {
        $this->assertEquals(0, $this->object->count());
        $this->object->foo = 'bar';
        $this->assertEquals(1, $this->object->count());
    }

    public function testChanged() {
        $this->object->mapData(['foo' => 'bar']);

        $this->assertFalse($this->object->changed());

        $this->object->foo = 'baz';

        $this->assertTrue($this->object->changed());
    }

    public function testChangedSameValue() {
        $this->object->mapData(['foo' => 'bar']);

        $this->assertFalse($this->object->changed());

        $this->object->foo = 'bar';

        $this->assertFalse($this->object->changed());
    }

    public function testChangedOnRelation() {
        $this->object->mapData(['foo' => 'bar']);

        $this->assertFalse($this->object->changed());

        $this->object->Profile = new Profile();

        $this->assertFalse($this->object->changed());
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
        $this->assertEquals('Miles', $user['firstName']);
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

    public function testGetChanged() {
        $this->object->mapData([
            'foo' => 'bar',
            'key' => 'value'
        ]);

        $this->assertEquals([], $this->object->getChanged());

        $this->object->foo = 'baz';

        $this->assertEquals([
            'foo' => 'baz'
        ], $this->object->getChanged());
    }

    public function testGetChangedNoRelation() {
        $this->object->mapData([
            'foo' => 'bar',
            'key' => 'value'
        ]);

        $this->assertEquals([], $this->object->getChanged());

        $this->object->foo = 'baz';
        $this->object->Profile = new Profile();

        $this->assertEquals([
            'foo' => 'baz'
        ], $this->object->getChanged());
    }

    public function testGetDisplayField() {
        $this->assertEquals(['title', 'name', 'id'], $this->object->getDisplayField());
    }

    public function testGetIterator() {
        $this->object->mapData(['a' => 1, 'b' => 2, 'c' => 3]);

        $data = [];

        foreach ($this->object as $key => $value) {
            $data[$key] = $value;
        }

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $data);
    }

    public function testGetOriginal() {
        $this->object->mapData([
            'foo' => 'bar',
            'key' => 'value'
        ]);

        $this->assertEquals([
            'foo' => 'bar',
            'key' => 'value'
        ], $this->object->getOriginal());

        $this->object->foo = 'baz';

        $this->assertEquals([
            'foo' => 'bar',
            'key' => 'value'
        ], $this->object->getOriginal());
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

    public function testIsDirty() {
        $this->object->mapData(['foo' => 'bar']);

        $this->assertFalse($this->object->isDirty('foo'));

        $this->object->foo = 'baz';

        $this->assertTrue($this->object->isDirty('foo'));
    }

    public function testIsDirtySameValue() {
        $this->object->mapData(['foo' => 'bar']);

        $this->assertFalse($this->object->isDirty('foo'));

        $this->object->foo = 'bar';

        $this->assertFalse($this->object->isDirty('foo'));
    }

    public function testIsDirtyNoValue() {
        $this->assertFalse($this->object->isDirty('foo'));

        $this->object->foo = 'bar';

        $this->assertTrue($this->object->isDirty('foo'));
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

    public function testJsonSerialize() {
        $user = new User([
            'id' => 1,
            'country_id' => 1,
            'username' => 'miles',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null,
            'Profile' => new Profile([
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-02-15 21:22:34',
                'currentLogin' => '2013-06-06 19:11:03'
            ])
        ]);

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
            'modified' => null,
            'Profile' => [
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-02-15 21:22:34',
                'currentLogin' => '2013-06-06 19:11:03'
            ]
        ], $user->jsonSerialize());
    }

    public function testLink() {
        $profile = new Profile(['foo' => 'bar']);

        $this->object->link($profile);

        $this->assertEquals(new EntityCollection([$profile]), $this->object->getRelation('Profile')->getLinked());
    }

    /**
     * @expectedException \Titon\Model\Exception\MissingRelationException
     */
    public function testLinkErrorsInvalidRelation() {
        $this->object->link(new Book());
    }

    public function testLinkMany() {
        $book = new Book(['name' => 'A Game Of Thrones']);
        $genre1 = new Genre(['name' => 'Horror']);
        $genre2 = new Genre(['name' => 'Action']);

        $book->linkMany($genre1, $genre2);

        $this->assertEquals(new EntityCollection([$genre1, $genre2]), $book->getRelation('Genres')->getLinked());
    }

    public function testLinkManyArray() {
        $book = new Book(['name' => 'A Game Of Thrones']);
        $genre1 = new Genre(['name' => 'Horror']);
        $genre2 = new Genre(['name' => 'Action']);

        $book->linkMany([$genre1, $genre2]);

        $this->assertEquals(new EntityCollection([$genre1, $genre2]), $book->getRelation('Genres')->getLinked());
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

    public function testQuery() {
        $query = $this->object->query(Query::SELECT);

        $this->assertInstanceOf('Titon\Model\QueryBuilder', $query);
        $this->assertInstanceOf('Titon\Db\Query', $query->getQuery());
        $this->assertEquals('select', $query->getQuery()->getType());
    }

    public function testRemove() {
        $user = new User();
        $user->foo = 'bar';

        $this->assertTrue(isset($user->foo));
        unset($user->foo);
        $this->assertFalse(isset($user->foo));

        $user->foo = 'baz';

        $this->assertTrue($user->has('foo'));
        $user->remove('foo');
        $this->assertFalse($user->has('foo'));
    }

    public function testSet() {
        $user = new User();

        $user->email = 'miles@email.com';
        $this->assertEquals('miles@email.com', $user->get('email'));

        $user['email'] = 'milesjohnson@email.com';
        $this->assertEquals('milesjohnson@email.com', $user->get('email'));
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

    public function testSerialize() {
        $user = new User([
            'id' => 1,
            'country_id' => 1,
            'username' => 'miles',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null,
            'Profile' => new Profile([
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-02-15 21:22:34',
                'currentLogin' => '2013-06-06 19:11:03'
            ])
        ]);

        $object = serialize($user);
        $this->assertEquals('C:26:"Titon\Test\Stub\Model\User":456:{a:11:{s:2:"id";i:1;s:10:"country_id";i:1;s:8:"username";s:5:"miles";s:9:"firstName";s:5:"Miles";s:8:"lastName";s:7:"Johnson";s:8:"password";s:15:"1Z5895jf72yL77h";s:5:"email";s:15:"miles@email.com";s:3:"age";i:25;s:7:"created";s:19:"1988-02-26 21:22:34";s:8:"modified";N;s:7:"Profile";C:29:"Titon\Test\Stub\Model\Profile":127:{a:4:{s:2:"id";i:4;s:7:"user_id";i:1;s:9:"lastLogin";s:19:"2012-02-15 21:22:34";s:12:"currentLogin";s:19:"2013-06-06 19:11:03";}}}}', $object);

        $user = unserialize($object);
        $this->assertEquals(new User([
            'id' => 1,
            'country_id' => 1,
            'username' => 'miles',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null,
            'Profile' => new Profile([
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-02-15 21:22:34',
                'currentLogin' => '2013-06-06 19:11:03'
            ])
        ]), $user);
    }

    public function testToArray() {
        $user = new User([
            'id' => 1,
            'country_id' => 1,
            'username' => 'miles',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null,
            'Profile' => new Profile([
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-02-15 21:22:34',
                'currentLogin' => '2013-06-06 19:11:03'
            ])
        ]);

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
            'modified' => null,
            'Profile' => [
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-02-15 21:22:34',
                'currentLogin' => '2013-06-06 19:11:03'
            ]
        ], $user->toArray());
    }

    public function testToJson() {
        $user = new User([
            'id' => 1,
            'country_id' => 1,
            'username' => 'miles',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null,
            'Profile' => new Profile([
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-02-15 21:22:34',
                'currentLogin' => '2013-06-06 19:11:03'
            ])
        ]);

        $this->assertEquals('{"id":1,"country_id":1,"username":"miles","firstName":"Miles","lastName":"Johnson","password":"1Z5895jf72yL77h","email":"miles@email.com","age":25,"created":"1988-02-26 21:22:34","modified":null,"Profile":{"id":4,"user_id":1,"lastLogin":"2012-02-15 21:22:34","currentLogin":"2013-06-06 19:11:03"}}', $user->toJson());
    }

    public function testUnlink() {
        $profile = new Profile(['foo' => 'bar']);

        $this->object->unlink($profile);

        $this->assertEquals(new EntityCollection([$profile]), $this->object->getRelation('Profile')->getUnlinked());
    }

    /**
     * @expectedException \Titon\Model\Exception\MissingRelationException
     */
    public function testUnlinkErrorsInvalidRelation() {
        $this->object->unlink(new Book());
    }

    public function testUnlinkMany() {
        $book = new Book(['name' => 'A Game Of Thrones']);
        $genre1 = new Genre(['name' => 'Horror']);
        $genre2 = new Genre(['name' => 'Action']);

        $book->unlinkMany($genre1, $genre2);

        $this->assertEquals(new EntityCollection([$genre1, $genre2]), $book->getRelation('Genres')->getUnlinked());
    }

    public function testUnlinkManyArray() {
        $book = new Book(['name' => 'A Game Of Thrones']);
        $genre1 = new Genre(['name' => 'Horror']);
        $genre2 = new Genre(['name' => 'Action']);

        $book->unlinkMany([$genre1, $genre2]);

        $this->assertEquals(new EntityCollection([$genre1, $genre2]), $book->getRelation('Genres')->getUnlinked());
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