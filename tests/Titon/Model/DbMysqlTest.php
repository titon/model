<?php
namespace Titon\Model;

use Titon\Common\Config;
use Titon\Db\Database;
use Titon\Db\Entity;
use Titon\Db\EntityCollection;
use Titon\Db\Mysql\MysqlDriver;
use Titon\Db\Query;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Country;
use Titon\Test\Stub\Model\Genre;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\User;
use Titon\Test\Stub\Model\Series;

class DbMysqlTest extends AbstractDbTest {

    protected function setUp() {
        parent::setUp();

        Database::registry()->addDriver('default', new MysqlDriver(Config::get('db')));
    }

    public function testReadSingleWithOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $actual = User::select()
            ->with('Profile')
            ->where('id', 1)
            ->first();

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
        ]), $actual);
    }

    public function testReadMultipleWithOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $actual = User::select()
            ->fields('id', 'username')
            ->with('Profile', function(Query $query) {
                $query->fields('id', 'user_id');
            })
            ->orderBy('id', 'asc')
            ->all();

        $this->assertEquals(new EntityCollection([
             new User([
                'id' => 1,
                'username' => 'miles',
                'Profile' => new Profile([
                    'id' => 4,
                    'user_id' => 1
                ])
            ]),
            new User([
                'id' => 2,
                'username' => 'batman',
                'Profile' => new Profile([
                    'id' => 5,
                    'user_id' => 2
                ])
            ]),
            new User([
                'id' => 3,
                'username' => 'superman',
                'Profile' => new Profile([
                    'id' => 2,
                    'user_id' => 3
                ])
            ]),
            new User([
                'id' => 4,
                'username' => 'spiderman',
                'Profile' => new Profile([
                    'id' => 1,
                    'user_id' => 4
                ])
            ]),
            new User([
                'id' => 5,
                'username' => 'wolverine',
                'Profile' => new Profile([
                    'id' => 3,
                    'user_id' => 5
                ])
            ])
        ]), $actual);
    }

    public function testReadSingleWithOneToMany() {
        $this->loadFixtures(['Books', 'Series']);

        $actual = Series::select()
            ->with('Books')
            ->where('id', 1)
            ->first();

        $this->assertEquals(new Series([
            'id' => 1,
            'author_id' => 1,
            'name' => 'A Song of Ice and Fire',
            'Books' => new EntityCollection([
                new Book(['id' => 1, 'series_id' => 1, 'name' => 'A Game of Thrones', 'isbn' => '0-553-10354-7', 'released' => '1996-08-02']),
                new Book(['id' => 2, 'series_id' => 1, 'name' => 'A Clash of Kings', 'isbn' => '0-553-10803-4', 'released' => '1999-02-25']),
                new Book(['id' => 3, 'series_id' => 1, 'name' => 'A Storm of Swords', 'isbn' => '0-553-10663-5', 'released' => '2000-11-11']),
                new Book(['id' => 4, 'series_id' => 1, 'name' => 'A Feast for Crows', 'isbn' => '0-553-80150-3', 'released' => '2005-11-02']),
                new Book(['id' => 5, 'series_id' => 1, 'name' => 'A Dance with Dragons', 'isbn' => '0-553-80147-3', 'released' => '2011-07-19']),
            ])
        ]), $actual);
    }

    public function testReadMultipleWithOneToMany() {
        $this->loadFixtures(['Books', 'Series']);

        $actual = Series::select()
            ->with('Books')
            ->where('id', [1, 3])
            ->orderBy('id', 'asc')
            ->all();

        $this->assertEquals(new EntityCollection([
             new Series([
                'id' => 1,
                'author_id' => 1,
                'name' => 'A Song of Ice and Fire',
                'Books' => new EntityCollection([
                    new Book(['id' => 1, 'series_id' => 1, 'name' => 'A Game of Thrones', 'isbn' => '0-553-10354-7', 'released' => '1996-08-02']),
                    new Book(['id' => 2, 'series_id' => 1, 'name' => 'A Clash of Kings', 'isbn' => '0-553-10803-4', 'released' => '1999-02-25']),
                    new Book(['id' => 3, 'series_id' => 1, 'name' => 'A Storm of Swords', 'isbn' => '0-553-10663-5', 'released' => '2000-11-11']),
                    new Book(['id' => 4, 'series_id' => 1, 'name' => 'A Feast for Crows', 'isbn' => '0-553-80150-3', 'released' => '2005-11-02']),
                    new Book(['id' => 5, 'series_id' => 1, 'name' => 'A Dance with Dragons', 'isbn' => '0-553-80147-3', 'released' => '2011-07-19']),
                ])
            ]),
            new Series([
                'id' => 3,
                'author_id' => 3,
                'name' => 'The Lord of the Rings',
                'Books' => new EntityCollection([
                    new Book(['id' => 13, 'series_id' => 3, 'name' => 'The Fellowship of the Ring', 'isbn' => '', 'released' => '1954-07-24']),
                    new Book(['id' => 14, 'series_id' => 3, 'name' => 'The Two Towers', 'isbn' => '', 'released' => '1954-11-11']),
                    new Book(['id' => 15, 'series_id' => 3, 'name' => 'The Return of the King', 'isbn' => '', 'released' => '1955-10-25']),
                ])
            ])
        ]), $actual);
    }

    public function testReadSingleWithManyToOne() {
        $this->loadFixtures(['Users', 'Countries']);

        $actual = User::select()
            ->with('Country')
            ->where('id', 1)
            ->first();

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
            'Country' => new Country([
                'id' => 1,
                'name' => 'United States of America',
                'iso' => 'USA'
            ])
        ]), $actual);
    }

    public function testReadMultipleWithManyToOne() {
        $this->loadFixtures(['Users', 'Countries']);

        $actual = User::select()
            ->fields('id', 'username')
            ->with('Country')
            ->orderBy('id', 'asc')
            ->all();

        $this->assertEquals(new EntityCollection([
             new User([
                'id' => 1,
                'username' => 'miles',
                'country_id' => 1,
                'Country' => new Country([
                    'id' => 1,
                    'name' => 'United States of America',
                    'iso' => 'USA'
                ])
            ]),
            new User([
                'id' => 2,
                'username' => 'batman',
                'country_id' => 3,
                'Country' => new Country([
                    'id' => 3,
                    'name' => 'England',
                    'iso' => 'ENG'
                ])
            ]),
            new User([
                'id' => 3,
                'username' => 'superman',
                'country_id' => 2,
                'Country' => new Country([
                    'id' => 2,
                    'name' => 'Canada',
                    'iso' => 'CAN'
                ])
            ]),
            new User([
                'id' => 4,
                'username' => 'spiderman',
                'country_id' => 5,
                'Country' => new Country([
                    'id' => 5,
                    'name' => 'Mexico',
                    'iso' => 'MEX'
                ])
            ]),
            new User([
                'id' => 5,
                'username' => 'wolverine',
                'country_id' => 4,
                'Country' => new Country([
                    'id' => 4,
                    'name' => 'Australia',
                    'iso' => 'AUS'
                ])
            ])
        ]), $actual);
    }

    public function testReadSingleWithManyToMany() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres']);

        $actual = Book::select()
            ->where('id', 5)
            ->with('Genres')
            ->first();

        $this->assertEquals(new Book([
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
                    'junction' => new Entity([
                        'id' => 14,
                        'book_id' => 5,
                        'genre_id' => 3
                    ])
                ]),
                new Genre([
                    'id' => 5,
                    'name' => 'Horror',
                    'book_count' => 5,
                    'junction' => new Entity([
                        'id' => 15,
                        'book_id' => 5,
                        'genre_id' => 5
                    ])
                ]),
                new Genre([
                    'id' => 8,
                    'name' => 'Fantasy',
                    'book_count' => 15,
                    'junction' => new Entity([
                        'id' => 13,
                        'book_id' => 5,
                        'genre_id' => 8
                    ])
                ]),
            ])
        ]), $actual);
    }

    public function testReadMultipleWithManyToMany() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres']);

        $actual = Book::select()
            ->where('series_id', 3)
            ->with('Genres')
            ->all();

        $this->assertEquals(new EntityCollection([
            new Book([
                'id' => 13,
                'series_id' => 3,
                'name' => 'The Fellowship of the Ring',
                'isbn' => '',
                'released' => '1954-07-24',
                'Genres' => new EntityCollection([
                    new Genre([
                        'id' => 3,
                        'name' => 'Action-Adventure',
                        'book_count' => 8,
                        'junction' => new Entity([
                            'id' => 38,
                            'book_id' => 13,
                            'genre_id' => 3
                        ])
                    ]),
                    new Genre([
                        'id' => 6,
                        'name' => 'Thriller',
                        'book_count' => 3,
                        'junction' => new Entity([
                            'id' => 39,
                            'book_id' => 13,
                            'genre_id' => 6
                        ])
                    ]),
                    new Genre([
                        'id' => 8,
                        'name' => 'Fantasy',
                        'book_count' => 15,
                        'junction' => new Entity([
                            'id' => 37,
                            'book_id' => 13,
                            'genre_id' => 8
                        ])
                    ]),
                ])
            ]),
            new Book([
                'id' => 14,
                'series_id' => 3,
                'name' => 'The Two Towers',
                'isbn' => '',
                'released' => '1954-11-11',
                'Genres' => new EntityCollection([
                    new Genre([
                        'id' => 3,
                        'name' => 'Action-Adventure',
                        'book_count' => 8,
                        'junction' => new Entity([
                            'id' => 41,
                            'book_id' => 14,
                            'genre_id' => 3
                        ])
                    ]),
                    new Genre([
                        'id' => 6,
                        'name' => 'Thriller',
                        'book_count' => 3,
                        'junction' => new Entity([
                            'id' => 42,
                            'book_id' => 14,
                            'genre_id' => 6
                        ])
                    ]),
                    new Genre([
                        'id' => 8,
                        'name' => 'Fantasy',
                        'book_count' => 15,
                        'junction' => new Entity([
                            'id' => 40,
                            'book_id' => 14,
                            'genre_id' => 8
                        ])
                    ]),
                ])
            ]),
            new Book([
                'id' => 15,
                'series_id' => 3,
                'name' => 'The Return of the King',
                'isbn' => '',
                'released' => '1955-10-25',
                'Genres' => new EntityCollection([
                    new Genre([
                        'id' => 3,
                        'name' => 'Action-Adventure',
                        'book_count' => 8,
                        'junction' => new Entity([
                            'id' => 44,
                            'book_id' => 15,
                            'genre_id' => 3
                        ])
                    ]),
                    new Genre([
                        'id' => 6,
                        'name' => 'Thriller',
                        'book_count' => 3,
                        'junction' => new Entity([
                            'id' => 45,
                            'book_id' => 15,
                            'genre_id' => 6
                        ])
                    ]),
                    new Genre([
                        'id' => 8,
                        'name' => 'Fantasy',
                        'book_count' => 15,
                        'junction' => new Entity([
                            'id' => 43,
                            'book_id' => 15,
                            'genre_id' => 8
                        ])
                    ]),
                ])
            ]),
        ]), $actual);
    }

}