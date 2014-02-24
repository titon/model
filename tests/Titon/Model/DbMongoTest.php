<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Exception;
use Titon\Common\Config;
use Titon\Db\Database;
use Titon\Db\EntityCollection;
use Titon\Db\Mongo\MongoDriver;
use Titon\Db\Query;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Test class for MongoDB.
 */
class DbMongoTest extends TestCase {

    /**
     * Setup the DB once, not before every test.
     */
    public static function setUpBeforeClass() {
        Database::registry()
            ->addDriver('default', new MongoDriver(Config::get('db')));

        // Remove singletons
        User::flushInstances();
        Book::flushInstances();
        Series::flushInstances();
        Profile::flushInstances();
    }

    /**
     * Test records are created.
     */
    public function testCreate() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->country_id = 1;
        $user->username = 'ironman';
        $user->firstName = 'Tony';
        $user->lastName = 'Stark';
        $user->password = '7NAks9193KAkjs1';
        $user->email = 'ironman@email.com';
        $user->age = 38;

        $last_id = $user->save(['validate' => false]);
        $this->assertInstanceOf('MongoId', $last_id);

        $this->assertEquals([
            '_id' => $last_id,
            'country_id' => 1,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'age' => 38
        ], User::find($last_id)->toArray());
    }

    /**
     * Test record creation with insert().
     */
    public function testCreateSingle() {
        $this->loadFixtures('Users');

        $last_id = User::insert([
            'country_id' => 1,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'age' => 38
        ]);

        $this->assertInstanceOf('MongoId', $last_id);

        $this->assertEquals([
            '_id' => $last_id,
            'country_id' => 1,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'age' => 38,
        ], User::find($last_id)->toArray());
    }

    /**
     * Test record creation with insertMany().
     */
    public function testCreateMultiple() {
        $this->loadFixtures('Users');

        User::truncate();

        $this->assertEquals(0, User::total());

        $this->assertEquals(5, User::insertMany([
            ['country_id' => 1, 'username' => 'miles', 'firstName' => 'Miles', 'lastName' => 'Johnson', 'password' => '1Z5895jf72yL77h', 'email' => 'miles@email.com', 'age' => 25, 'created' => '1988-02-26 21:22:34'],
            ['country_id' => 3, 'username' => 'batman', 'firstName' => 'Bruce', 'lastName' => 'Wayne', 'created' => '1960-05-11 21:22:34'],
            ['country_id' => 2, 'username' => 'superman', 'email' => 'superman@email.com', 'age' => 33, 'created' => '1970-09-18 21:22:34'],
            ['country_id' => 5, 'username' => 'spiderman', 'firstName' => 'Peter', 'lastName' => 'Parker', 'password' => '1Z5895jf72yL77h', 'email' => 'spiderman@email.com', 'age' => 22, 'created' => '1990-01-05 21:22:34'],
            ['country_id' => 4, 'username' => 'wolverine', 'password' => '1Z5895jf72yL77h', 'email' => 'wolverine@email.com'],
        ]));

        $this->assertEquals(5, User::total());
    }

    /**
     * Test that create fails with empty data.
     */
    public function testCreateEmptyData() {
        $this->loadFixtures('Users');

        $user = new User();

        try {
            $this->assertSame(0, $user->save());
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Relation without data
        try {
            $user->Profile = [
                'lastLogin' => time()
            ];

            $this->assertSame(0, $user->save());
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test reading data through select().
     */
    public function testRead() {
        $this->loadFixtures('Books');

        // Single
        $this->assertEquals(new Book([
            'series_id' => 1,
            'name' => 'A Game of Thrones',
            'isbn' => '0-553-10354-7',
            'released' => '1996-08-02'
        ]), Book::select('series_id', 'name', 'isbn', 'released')->orderBy('_id', 'asc')->first());

        // Multiple
        $this->assertEquals(new EntityCollection([
            new Book([
                'series_id' => 3,
                'name' => 'The Fellowship of the Ring',
                'isbn' => '',
                'released' => '1954-07-24'
            ]),
            new Book([
                'series_id' => 3,
                'name' => 'The Two Towers',
                'isbn' => '',
                'released' => '1954-11-11'
            ]),
            new Book([
                'series_id' => 3,
                'name' => 'The Return of the King',
                'isbn' => '',
                'released' => '1955-10-25'
            ]),
        ]), Book::select('series_id', 'name', 'isbn', 'released')->where('series_id', 3)->orderBy('_id', 'asc')->all());
    }

    /**
     * Test updating a single record.
     */
    public function testUpdate() {
        $this->loadFixtures('Users');

        $first = User::select()->orderBy('_id', 'asc')->first();

        $user = new User();
        $user->_id = $first->_id;
        $user->country_id = 3;
        $user->username = 'milesj';

        $last_id = $user->save();
        $this->assertInstanceOf('MongoId', $last_id);

        $this->assertEquals(new User([
            '_id' => $last_id,
            'country_id' => 3,
            'username' => 'milesj',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => 25,
            'created' => '1988-02-26 21:22:34'
        ]), User::select()->where('_id', $last_id)->first());
    }

    /**
     * Test updating a single record via static method.
     */
    public function testUpdateSingle() {
        $this->loadFixtures('Users');

        $first = User::select()->orderBy('_id', 'asc')->first();

        $this->assertEquals(1, User::updateBy($first->_id, [
            'country_id' => 3,
            'username' => 'milesj'
        ]));

        $this->assertEquals(new User([
            '_id' => $first->_id,
            'country_id' => 3,
            'username' => 'milesj',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => 25,
            'created' => '1988-02-26 21:22:34'
        ]), User::select()->where('_id', $first->_id)->first());
    }

    /**
     * Test multiple record updates.
     */
    public function testUpdateMultiple() {
        $this->loadFixtures('Users');

        $this->assertSame(4, User::updateMany(['country_id' => 1], function(Query $query) {
            $query->where('country_id', '!=', 1);
        }));

        $this->assertEquals(new EntityCollection([
            new User(['country_id' => 1, 'username' => 'miles']),
            new User(['country_id' => 1, 'username' => 'batman']),
            new User(['country_id' => 1, 'username' => 'superman']),
            new User(['country_id' => 1, 'username' => 'spiderman']),
            new User(['country_id' => 1, 'username' => 'wolverine']),
        ]), User::select('country_id', 'username')->orderBy('_id', 'asc')->all());

        // No where clause
        $this->assertSame(5, User::updateMany(['country_id' => 2], function(Query $query) {
            $query->where('country_id', '!=', 1000); // high number = all
        }));

        $this->assertEquals(new EntityCollection([
            new User(['country_id' => 2, 'username' => 'miles']),
            new User(['country_id' => 2, 'username' => 'batman']),
            new User(['country_id' => 2, 'username' => 'superman']),
            new User(['country_id' => 2, 'username' => 'spiderman']),
            new User(['country_id' => 2, 'username' => 'wolverine']),
        ]), User::select('country_id', 'username')->orderBy('_id', 'asc')->all());
    }

    /**
     * Test updating with empty data.
     */
    public function testUpdateEmptyData() {
        $this->loadFixtures('Users');

        $user = new User();

        try {
            $this->assertEquals(0, $user->save());
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Relation without data
        try {
            $user->Profile = [
                'lastLogin' => time()
            ];
            $user->save();
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test database record updating against unique columns.
     */
    public function testUpdateUniqueColumn() {
        $this->loadFixtures('Users');

        $first = User::select()->orderBy('_id', 'asc')->first();

        $user = new User();
        $user->id = $first->_id;
        $user->username = 'batman'; // name already exists

        try {
            $this->assertEquals(1, $user->save());
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test single record deletion.
     */
    public function testDelete() {
        $this->loadFixtures('Users');

        $last = User::select()->orderBy('_id', 'asc')->first();
        $user = User::find($last->_id);

        $this->assertTrue($user->exists());
        $this->assertSame(1, $user->delete(false));
        $this->assertFalse($user->exists());
    }

    /**
     * Test delete with where conditions.
     */
    public function testDeleteConditions() {
        $this->loadFixtures('Users', 'Profiles');

        $this->assertSame(5, User::total());
        $this->assertSame(3, User::deleteMany(function(Query $query) {
            $query->where('age', '>', 30);
        }));
        $this->assertSame(2, User::total());
    }

    /**
     * Test multiple deletion through conditions.
     */
    public function testDeleteMany() {
        $this->loadFixtures(['Users', 'Profiles']);

        // Throws exceptions if no conditions applied
        try {
            User::deleteMany(function() {
                // Nothing
            });
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $this->assertEquals(3, User::deleteMany(function(Query $query) {
            $query->where('age', '>', 30);
        }));
    }

}