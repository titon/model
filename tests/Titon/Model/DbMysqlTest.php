<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Exception;
use Titon\Common\Config;
use Titon\Common\Registry;
use Titon\Db\Entity;
use Titon\Db\Mysql\MysqlDriver;
use Titon\Db\Query;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Test class for MySQL.
 */
class DbMysqlTest extends TestCase {

    /**
     * Set the mysql driver.
     */
    protected function setUp() {
        parent::setUp();

        Registry::factory('Titon\Db\Connection')
            ->addDriver(new MysqlDriver('default', Config::get('db')));
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

        $this->assertEquals(6, $user->save(['validate' => false]));

        $this->assertEquals([
            'id' => 6,
            'country_id' => 1,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'age' => 38,
            'created' => '',
            'modified' => ''
        ], User::find(6)->toArray());
    }

    /**
     * Test record creation with insert().
     */
    public function testCreateSingle() {
        $this->loadFixtures('Users');

        $this->assertEquals(6, User::insert([
            'country_id' => 1,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'age' => 38
        ]));

        $this->assertEquals([
            'id' => 6,
            'country_id' => 1,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'age' => 38,
            'created' => '',
            'modified' => ''
        ], User::find(6)->toArray());
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
     * Test create with one-to-one relation.
     */
    public function testCreateOneToOne() {
        $this->loadFixtures('Users', 'Profiles');

        $user = new User();
        $user->country_id = 1;
        $user->username = 'ironman';
        $user->firstName = 'Tony';
        $user->lastName = 'Stark';
        $user->password = '7NAks9193KAkjs1';
        $user->email = 'ironman@email.com';
        $user->age = 38;
        $user->Profile = [
            'lastLogin' => '2012-06-24 17:30:33'
        ];

        $this->assertEquals(6, $user->save(['validate' => false]));

        $this->assertEquals([
            'id' => 6,
            'country_id' => 1,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'age' => 38,
            'created' => '',
            'modified' => '',
            'Profile' => [
                'id' => 6,
                'user_id' => 6,
                'lastLogin' => '2012-06-24 17:30:33',
                'currentLogin' => ''
            ]
        ], User::select()->where('id', 6)->with('Profile')->fetch()->toArray());
    }

    /**
     * Test create with many-to-many relation.
     */
    public function testCreateManyToMany() {
        $this->loadFixtures(['Genres', 'Books', 'BookGenres']);

        $book = new Book();
        $book->series_id = 1;
        $book->name = 'The Winds of Winter';
        $book->Genres = [
            ['id' => 3, 'name' => 'Action-Adventure'], // Existing genre
            ['name' => 'Epic-Horror'], // New genre
            ['genre_id' => 8] // Existing genre by ID
        ];

        $this->assertEquals(16, $book->save());

        $this->assertEquals([
            'id' => 16,
            'series_id' => 1,
            'name' => 'The Winds of Winter',
            'isbn' => '',
            'released' => '',
            'Genres' => [
                [
                    'id' => 3,
                    'name' => 'Action-Adventure',
                    'book_count' => 8,
                    'Junction' => [
                        'id' => 46,
                        'book_id' => 16,
                        'genre_id' => 3,
                    ]
                ],
                [
                    'id' => 8,
                    'name' => 'Fantasy',
                    'book_count' => 15,
                    'Junction' => [
                        'id' => 48,
                        'book_id' => 16,
                        'genre_id' => 8,
                    ]
                ],
                [
                    'id' => 12,
                    'name' => 'Epic-Horror',
                    'book_count' => 0,
                    'Junction' => [
                        'id' => 47,
                        'book_id' => 16,
                        'genre_id' => 12,
                    ]
                ],
            ]
        ], Book::select()->where('id', 16)->with('Genres')->fetch()->toArray());
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
        $this->assertEquals(new Entity([
            'id' => 5,
            'series_id' => 1,
            'name' => 'A Dance with Dragons',
            'isbn' => '0-553-80147-3',
            'released' => '2011-07-19'
        ]), Book::select()->where('id', 5)->fetch());

        // Multiple
        $this->assertEquals([
            new Entity([
                'id' => 13,
                'series_id' => 3,
                'name' => 'The Fellowship of the Ring',
                'isbn' => '',
                'released' => '1954-07-24'
            ]),
            new Entity([
                'id' => 14,
                'series_id' => 3,
                'name' => 'The Two Towers',
                'isbn' => '',
                'released' => '1954-11-11'
            ]),
            new Entity([
                'id' => 15,
                'series_id' => 3,
                'name' => 'The Return of the King',
                'isbn' => '',
                'released' => '1955-10-25'
            ]),
        ], Book::select()->where('series_id', 3)->orderBy('id', 'asc')->fetchAll());
    }

    /**
     * Test complex select queries.
     */
    public function testReadWithComplexRelations() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres', 'Series']);

        $actual = Series::select('id', 'name')
            ->where('id', 1)
            ->with('Books', function(Query $query) {
                $query->with('Genres');
            })
            ->fetch(['eager' => true]);

        $this->assertEquals(new Entity([
            'id' => 1,
            'name' => 'A Song of Ice and Fire',
            'Books' => [
                new Entity([
                    'id' => 1,
                    'series_id' => 1,
                    'name' => 'A Game of Thrones',
                    'isbn' => '0-553-10354-7',
                    'released' => '1996-08-02',
                    'Genres' => [
                        new Entity([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new Entity([
                                'id' => 2,
                                'book_id' => 1,
                                'genre_id' => 3
                            ])
                        ]),
                        new Entity([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new Entity([
                                'id' => 3,
                                'book_id' => 1,
                                'genre_id' => 5
                            ])
                        ]),
                        new Entity([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new Entity([
                                'id' => 1,
                                'book_id' => 1,
                                'genre_id' => 8
                            ])
                        ]),
                    ]
                ]),
                new Entity([
                    'id' => 2,
                    'series_id' => 1,
                    'name' => 'A Clash of Kings',
                    'isbn' => '0-553-10803-4',
                    'released' => '1999-02-25',
                    'Genres' => [
                        new Entity([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new Entity([
                                'id' => 5,
                                'book_id' => 2,
                                'genre_id' => 3
                            ])
                        ]),
                        new Entity([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new Entity([
                                'id' => 6,
                                'book_id' => 2,
                                'genre_id' => 5
                            ])
                        ]),
                        new Entity([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new Entity([
                                'id' => 4,
                                'book_id' => 2,
                                'genre_id' => 8
                            ])
                        ]),
                    ]
                ]),
                new Entity([
                    'id' => 3,
                    'series_id' => 1,
                    'name' => 'A Storm of Swords',
                    'isbn' => '0-553-10663-5',
                    'released' => '2000-11-11',
                    'Genres' => [
                        new Entity([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new Entity([
                                'id' => 8,
                                'book_id' => 3,
                                'genre_id' => 3
                            ])
                        ]),
                        new Entity([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new Entity([
                                'id' => 9,
                                'book_id' => 3,
                                'genre_id' => 5
                            ])
                        ]),
                        new Entity([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new Entity([
                                'id' => 7,
                                'book_id' => 3,
                                'genre_id' => 8
                            ])
                        ]),
                    ]
                ]),
                new Entity([
                    'id' => 4,
                    'series_id' => 1,
                    'name' => 'A Feast for Crows',
                    'isbn' => '0-553-80150-3',
                    'released' => '2005-11-02',
                    'Genres' => [
                        new Entity([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new Entity([
                                'id' => 11,
                                'book_id' => 4,
                                'genre_id' => 3
                            ])
                        ]),
                        new Entity([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new Entity([
                                'id' => 12,
                                'book_id' => 4,
                                'genre_id' => 5
                            ])
                        ]),
                        new Entity([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new Entity([
                                'id' => 10,
                                'book_id' => 4,
                                'genre_id' => 8
                            ])
                        ]),
                    ]
                ]),
                new Entity([
                    'id' => 5,
                    'series_id' => 1,
                    'name' => 'A Dance with Dragons',
                    'isbn' => '0-553-80147-3',
                    'released' => '2011-07-19',
                    'Genres' => [
                        new Entity([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new Entity([
                                'id' => 14,
                                'book_id' => 5,
                                'genre_id' => 3
                            ])
                        ]),
                        new Entity([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new Entity([
                                'id' => 15,
                                'book_id' => 5,
                                'genre_id' => 5
                            ])
                        ]),
                        new Entity([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new Entity([
                                'id' => 13,
                                'book_id' => 5,
                                'genre_id' => 8
                            ])
                        ]),
                    ]
                ]),
            ]
        ]), $actual);
    }

    /**
     * Test updating a single record.
     */
    public function testUpdate() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->id = 1;
        $user->country_id = 3;
        $user->username = 'milesj';

        $this->assertEquals(1, $user->save());

        $this->assertEquals(new Entity([
            'id' => 1,
            'country_id' => 3,
            'username' => 'milesj',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null
        ]), User::select()->where('id', 1)->fetch());
    }

    /**
     * Test updating a single record via static method.
     */
    public function testUpdateSingle() {
        $this->loadFixtures('Users');

        $this->assertEquals(1, User::updateBy(1, [
            'country_id' => 3,
            'username' => 'milesj'
        ]));

        $this->assertEquals(new Entity([
            'id' => 1,
            'country_id' => 3,
            'username' => 'milesj',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null
        ]), User::select()->where('id', 1)->fetch());
    }

    /**
     * Test multiple record updates.
     */
    public function testUpdateMultiple() {
        $this->loadFixtures('Users');

        $this->assertSame(4, User::updateMany(['country_id' => 1], function(Query $query) {
            $query->where('country_id', '!=', 1);
        }));

        $this->assertEquals([
            new Entity(['id' => 1, 'country_id' => 1, 'username' => 'miles']),
            new Entity(['id' => 2, 'country_id' => 1, 'username' => 'batman']),
            new Entity(['id' => 3, 'country_id' => 1, 'username' => 'superman']),
            new Entity(['id' => 4, 'country_id' => 1, 'username' => 'spiderman']),
            new Entity(['id' => 5, 'country_id' => 1, 'username' => 'wolverine']),
        ], User::select('id', 'country_id', 'username')->orderBy('id', 'asc')->fetchAll());

        // No where clause
        $this->assertSame(5, User::updateMany(['country_id' => 2], function(Query $query) {
            $query->where('country_id', '!=', '');
        }));

        $this->assertEquals([
            new Entity(['id' => 1, 'country_id' => 2, 'username' => 'miles']),
            new Entity(['id' => 2, 'country_id' => 2, 'username' => 'batman']),
            new Entity(['id' => 3, 'country_id' => 2, 'username' => 'superman']),
            new Entity(['id' => 4, 'country_id' => 2, 'username' => 'spiderman']),
            new Entity(['id' => 5, 'country_id' => 2, 'username' => 'wolverine']),
        ], User::select('id', 'country_id', 'username')->orderBy('id', 'asc')->fetchAll());
    }

    /**
     * Test database record updating with one to one relations.
     */
    public function testUpdateOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = new User();
        $user->id = 1;
        $user->country_id = 3;
        $user->username = 'milesj';
        $user->Profile = [
            'id' => 4,
            'lastLogin' => '2012-06-24 17:30:33'
        ];

        $this->assertEquals(1, $user->save());

        $this->assertEquals([
            'id' => 1,
            'country_id' => 3,
            'username' => 'milesj',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => '25',
            'created' => '1988-02-26 21:22:34',
            'modified' => '',
            'Profile' => [
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-06-24 17:30:33',
                'currentLogin' => '2013-06-06 19:11:03'
            ]
        ], User::select()->where('id', 1)->with('Profile')->fetch()->toArray());

        // Should throw errors for invalid array structure
        $user = new User();
        $user->id = 1;
        $user->country_id = 3;
        $user->username = 'milesj';
        $user->Profile = [
            ['lastLogin' => '2012-06-24 17:30:33'] // Nested array
        ];

        try {
            $this->assertEquals(1, $user->save());
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Will upsert if no one-to-one ID is present
        $user = new User();
        $user->id = 1;
        $user->country_id = 3;
        $user->username = 'miles';
        $user->Profile = [
            'currentLogin' => '2012-06-24 17:30:33' // Nested array
        ];

        $this->assertEquals(1, $user->save());

        $this->assertEquals([
            'id' => 1,
            'country_id' => 3,
            'username' => 'miles',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => '25',
            'created' => '1988-02-26 21:22:34',
            'modified' => '',
            'Profile' => [
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-06-24 17:30:33',
                'currentLogin' => '2013-06-06 19:11:03',
            ]
        ], User::select()->where('id', 1)->with('Profile')->fetch()->toArray());
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

        $user = new User();
        $user->id = 1;
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

        $user = User::find(1);

        $this->assertTrue($user->exists());
        $this->assertSame(1, $user->delete(false));
        $this->assertFalse($user->exists());
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

        $this->assertEquals(3, User::deleteMany(function() {
            $this->where('age', '>', 30);
        }));
    }

    /**
     * Test that one-to-one relation dependents are deleted.
     */
    public function testDeleteCascadeOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::find(2);

        $this->assertTrue($user->exists());
        $this->assertTrue(Profile::find(5)->exists());

        $user->delete(true);

        $this->assertFalse($user->exists());
        $this->assertFalse(Profile::find(5)->exists());
    }

    /**
     * Test that dependents aren't deleted if cascade is false.
     */
    public function testDeleteNoCascade() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::find(2);

        $this->assertTrue($user->exists());
        $this->assertTrue(Profile::find(5)->exists());

        $user->delete(false);

        $this->assertFalse($user->exists());
        $this->assertTrue(Profile::find(5)->exists());
    }

}