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
use Titon\Db\Mysql\MysqlDriver;
use Titon\Db\Query;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\BookGenre;
use Titon\Test\Stub\Model\Genre;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Test class for MySQL.
 */
class DbMysqlTestOld extends TestCase {

    /**
     * Setup the DB once, not before every test.
     */
    public static function setUpBeforeClass() {
        Database::registry()
            ->addDriver('default', new MysqlDriver(Config::get('db')));

        // Remove singletons
        User::flushInstances();
        Book::flushInstances();
        Series::flushInstances();
        Profile::flushInstances();
    }

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
        ], User::select()->where('id', 6)->with('Profile')->first()->toArray());
    }

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
        ], Book::select()->where('id', 16)->with('Genres', function(Query $query) {
            $query->orderBy('id', 'asc');
        })->first()->toArray());
    }

    public function testRead() {
        $this->loadFixtures('Books');

        // Single
        $this->assertEquals(new Book([
            'id' => 5,
            'series_id' => 1,
            'name' => 'A Dance with Dragons',
            'isbn' => '0-553-80147-3',
            'released' => '2011-07-19'
        ]), Book::select()->where('id', 5)->first());

        // Multiple
        $this->assertEquals(new EntityCollection([
            new Book([
                'id' => 13,
                'series_id' => 3,
                'name' => 'The Fellowship of the Ring',
                'isbn' => '',
                'released' => '1954-07-24'
            ]),
            new Book([
                'id' => 14,
                'series_id' => 3,
                'name' => 'The Two Towers',
                'isbn' => '',
                'released' => '1954-11-11'
            ]),
            new Book([
                'id' => 15,
                'series_id' => 3,
                'name' => 'The Return of the King',
                'isbn' => '',
                'released' => '1955-10-25'
            ]),
        ]), Book::select()->where('series_id', 3)->orderBy('id', 'asc')->all());
    }

    public function testReadWithComplexRelations() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres', 'Series']);

        $actual = Series::select('id', 'name')
            ->where('id', 1)
            ->with('Books', function(Query $query) {
                $query->orderBy('id', 'asc')->with('Genres', function(Query $query2) {
                    $query2->orderBy('id', 'asc');
                });
            })
            ->first(['eager' => true]);

        $this->assertEquals(new Series([
            'id' => 1,
            'name' => 'A Song of Ice and Fire',
            'Books' => new EntityCollection([
                new Book([
                    'id' => 1,
                    'series_id' => 1,
                    'name' => 'A Game of Thrones',
                    'isbn' => '0-553-10354-7',
                    'released' => '1996-08-02',
                    'Genres' => new EntityCollection([
                        new Genre([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new BookGenre([
                                'id' => 2,
                                'book_id' => 1,
                                'genre_id' => 3
                            ])
                        ]),
                        new Genre([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new BookGenre([
                                'id' => 3,
                                'book_id' => 1,
                                'genre_id' => 5
                            ])
                        ]),
                        new Genre([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new BookGenre([
                                'id' => 1,
                                'book_id' => 1,
                                'genre_id' => 8
                            ])
                        ]),
                    ])
                ]),
                new Book([
                    'id' => 2,
                    'series_id' => 1,
                    'name' => 'A Clash of Kings',
                    'isbn' => '0-553-10803-4',
                    'released' => '1999-02-25',
                    'Genres' => new EntityCollection([
                        new Genre([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new BookGenre([
                                'id' => 5,
                                'book_id' => 2,
                                'genre_id' => 3
                            ])
                        ]),
                        new Genre([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new BookGenre([
                                'id' => 6,
                                'book_id' => 2,
                                'genre_id' => 5
                            ])
                        ]),
                        new Genre([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new BookGenre([
                                'id' => 4,
                                'book_id' => 2,
                                'genre_id' => 8
                            ])
                        ]),
                    ])
                ]),
                new Book([
                    'id' => 3,
                    'series_id' => 1,
                    'name' => 'A Storm of Swords',
                    'isbn' => '0-553-10663-5',
                    'released' => '2000-11-11',
                    'Genres' => new EntityCollection([
                        new Genre([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new BookGenre([
                                'id' => 8,
                                'book_id' => 3,
                                'genre_id' => 3
                            ])
                        ]),
                        new Genre([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new BookGenre([
                                'id' => 9,
                                'book_id' => 3,
                                'genre_id' => 5
                            ])
                        ]),
                        new Genre([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new BookGenre([
                                'id' => 7,
                                'book_id' => 3,
                                'genre_id' => 8
                            ])
                        ]),
                    ])
                ]),
                new Book([
                    'id' => 4,
                    'series_id' => 1,
                    'name' => 'A Feast for Crows',
                    'isbn' => '0-553-80150-3',
                    'released' => '2005-11-02',
                    'Genres' => new EntityCollection([
                        new Genre([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new BookGenre([
                                'id' => 11,
                                'book_id' => 4,
                                'genre_id' => 3
                            ])
                        ]),
                        new Genre([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new BookGenre([
                                'id' => 12,
                                'book_id' => 4,
                                'genre_id' => 5
                            ])
                        ]),
                        new Genre([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new BookGenre([
                                'id' => 10,
                                'book_id' => 4,
                                'genre_id' => 8
                            ])
                        ]),
                    ])
                ]),
                new Book([
                    'id' => 5,
                    'series_id' => 1,
                    'name' => 'A Dance with Dragons',
                    'isbn' => '0-553-80147-3',
                    'released' => '2011-07-19',
                    'Genres' => new EntityCollection([
                        new Genre([
                            'id' => 3,
                            'name' => 'Action-Adventure',
                            'book_count' => 8,
                            'Junction' => new BookGenre([
                                'id' => 14,
                                'book_id' => 5,
                                'genre_id' => 3
                            ])
                        ]),
                        new Genre([
                            'id' => 5,
                            'name' => 'Horror',
                            'book_count' => 5,
                            'Junction' => new BookGenre([
                                'id' => 15,
                                'book_id' => 5,
                                'genre_id' => 5
                            ])
                        ]),
                        new Genre([
                            'id' => 8,
                            'name' => 'Fantasy',
                            'book_count' => 15,
                            'Junction' => new BookGenre([
                                'id' => 13,
                                'book_id' => 5,
                                'genre_id' => 8
                            ])
                        ]),
                    ])
                ]),
            ])
        ]), $actual);
    }

    public function testUpdate() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->id = 1;
        $user->country_id = 3;
        $user->username = 'milesj';

        $this->assertEquals(1, $user->save());

        $this->assertEquals(new User([
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
        ]), User::select()->where('id', 1)->first());
    }

    public function testUpdateSingle() {
        $this->loadFixtures('Users');

        $this->assertEquals(1, User::updateBy(1, [
            'country_id' => 3,
            'username' => 'milesj'
        ]));

        $this->assertEquals(new User([
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
        ]), User::select()->where('id', 1)->first());
    }

    public function testUpdateMultiple() {
        $this->loadFixtures('Users');

        $this->assertSame(4, User::updateMany(['country_id' => 1], function(Query $query) {
            $query->where('country_id', '!=', 1);
        }));

        $this->assertEquals(new EntityCollection([
            new User(['id' => 1, 'country_id' => 1, 'username' => 'miles']),
            new User(['id' => 2, 'country_id' => 1, 'username' => 'batman']),
            new User(['id' => 3, 'country_id' => 1, 'username' => 'superman']),
            new User(['id' => 4, 'country_id' => 1, 'username' => 'spiderman']),
            new User(['id' => 5, 'country_id' => 1, 'username' => 'wolverine']),
        ]), User::select('id', 'country_id', 'username')->orderBy('id', 'asc')->all());

        // No where clause
        $this->assertSame(5, User::updateMany(['country_id' => 2], function(Query $query) {
            $query->where('country_id', '!=', 1000); // high number = all
        }));

        $this->assertEquals(new EntityCollection([
            new User(['id' => 1, 'country_id' => 2, 'username' => 'miles']),
            new User(['id' => 2, 'country_id' => 2, 'username' => 'batman']),
            new User(['id' => 3, 'country_id' => 2, 'username' => 'superman']),
            new User(['id' => 4, 'country_id' => 2, 'username' => 'spiderman']),
            new User(['id' => 5, 'country_id' => 2, 'username' => 'wolverine']),
        ]), User::select('id', 'country_id', 'username')->orderBy('id', 'asc')->all());
    }

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
        ], User::select()->where('id', 1)->with('Profile')->first()->toArray());

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
        ], User::select()->where('id', 1)->with('Profile', function(Query $query) {
            $query->orderBy('id', 'asc'); // multiple records exist now
        })->first()->toArray());
    }

    public function testDeleteCascadeOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::find(2);

        $this->assertTrue($user->exists());
        $this->assertTrue(Profile::find(5)->exists());

        $user->delete(true);

        $this->assertFalse($user->exists());
        $this->assertFalse(Profile::find(5)->exists());
    }

    public function testDeleteNoCascade() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::find(2);

        $this->assertTrue($user->exists());
        $this->assertTrue(Profile::find(5)->exists());

        $user->delete(false);

        $this->assertFalse($user->exists());
        $this->assertTrue(Profile::find(5)->exists());
    }

    public function testUpsertNoId() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->username = 'ironman';

        $this->assertFalse(User::find(6)->exists());

        $this->assertEquals(6, $user->save());

        $this->assertTrue(User::find(6)->exists());
    }

    public function testUpsertWithId() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->id = 1;
        $user->username = 'ironman';

        $this->assertFalse(User::find(6)->exists());

        $this->assertEquals(1, $user->save());

        $this->assertFalse(User::find(6)->exists());
    }

    public function testUpsertWithFakeId() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->id = 10;
        $user->username = 'ironman';

        $this->assertFalse(User::find(6)->exists());

        $this->assertEquals(6, $user->save());

        $this->assertTrue(User::find(6)->exists());
    }

    public function testUpsertOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = new User();
        $time = date('Y-m-d H:i:s');

        // Update
        $this->assertEquals(new Profile([
            'id' => 4,
            'user_id' => 1,
            'lastLogin' => '2012-02-15 21:22:34',
            'currentLogin' => '2013-06-06 19:11:03'
        ]), Profile::select()->where('id', 4)->first());

        $user->id = 1;
        $user->username = 'milesj';
        $user->Profile = [
            'id' => 4,
            'lastLogin' => $time
        ];

        $this->assertEquals(1, $user->save());

        $this->assertEquals(new Profile([
            'id' => 4,
            'user_id' => 1,
            'lastLogin' => $time,
            'currentLogin' => '2013-06-06 19:11:03'
        ]), Profile::select()->where('id', 4)->first());

        // Create
        $this->assertFalse(Profile::find(6)->exists());

        $user->Profile = [
            'lastLogin' => $time
        ];

        $this->assertEquals(1, $user->save());

        $this->assertEquals(new Profile([
            'id' => 6,
            'user_id' => 1,
            'lastLogin' => date('Y-m-d H:i:s'),
            'currentLogin' => null
        ]), Profile::select()->where('id', 6)->first());
    }

    public function testUpsertWithManyToMany() {
        $this->loadFixtures(['Genres', 'Books', 'BookGenres']);

        // Trigger lazy-loaded queries
        $results = Book::select()->where('id', 10)->with('Genres')->first();
        $results->Genres;

        $this->assertEquals(new Book([
            'id' => 10,
            'series_id' => 2,
            'name' => 'Harry Potter and the Order of the Phoenix',
            'isbn' => '0-7475-5100-6',
            'released' => '2003-06-21',
            'Genres' => new EntityCollection([
                new Genre([
                    'id' => 2,
                    'name' => 'Adventure',
                    'book_count' => 7,
                    'Junction' => new BookGenre([
                        'id' => 29,
                        'book_id' => 10,
                        'genre_id' => 2
                    ])
                ]),
                new Genre([
                    'id' => 7,
                    'name' => 'Mystery',
                    'book_count' => 7,
                    'Junction' => new BookGenre([
                        'id' => 30,
                        'book_id' => 10,
                        'genre_id' => 7
                    ])
                ]),
                new Genre([
                    'id' => 8,
                    'name' => 'Fantasy',
                    'book_count' => 15,
                    'Junction' => new BookGenre([
                        'id' => 28,
                        'book_id' => 10,
                        'genre_id' => 8
                    ])
                ])
            ])
        ]), $results);

        $book = new Book();
        $book->id = 10;
        $book->released = '2003-06-21'; // needs a field or it wont update
        $book->Genres = [
            ['id' => 2, 'name' => 'Adventure (Updated)'], // Updated
            ['name' => 'Magic'], // Created
            ['id' => 125, 'name' => 'Wizardry'], // Created because of invalid ID
            ['genre_id' => 8, 'name' => 'Fantasy (Updated)'] // Updated because of direct foreign key
        ];

        $this->assertEquals(10, $book->save());

        // Trigger lazy-loaded queries
        $results = Book::select()->where('id', 10)->with('Genres', function(Query $query) {
            $query->orderBy('id', 'asc');
        })->first();
        $results->Genres;

        $this->assertEquals([
            'id' => 10,
            'series_id' => 2,
            'name' => 'Harry Potter and the Order of the Phoenix',
            'isbn' => '0-7475-5100-6',
            'released' => '2003-06-21',
            'Genres' => [
                [
                    'id' => 2,
                    'name' => 'Adventure (Updated)',
                    'book_count' => 7,
                    'Junction' => [
                        'id' => 29,
                        'book_id' => 10,
                        'genre_id' => 2
                    ]
                ], [
                    'id' => 7,
                    'name' => 'Mystery',
                    'book_count' => 7,
                    'Junction' => [
                        'id' => 30,
                        'book_id' => 10,
                        'genre_id' => 7
                    ]
                ], [
                    'id' => 8,
                    'name' => 'Fantasy (Updated)',
                    'book_count' => 15,
                    'Junction' => [
                        'id' => 28,
                        'book_id' => 10,
                        'genre_id' => 8
                    ]
                ], [
                    'id' => 12,
                    'name' => 'Magic',
                    'book_count' => 0,
                    'Junction' => [
                        'id' => 46,
                        'book_id' => 10,
                        'genre_id' => 12
                    ]
                ], [
                    'id' => 13,
                    'name' => 'Wizardry',
                    'book_count' => 0,
                    'Junction' => [
                        'id' => 47,
                        'book_id' => 10,
                        'genre_id' => 13
                    ]
                ]
            ]
        ], $results->toArray());
    }

}