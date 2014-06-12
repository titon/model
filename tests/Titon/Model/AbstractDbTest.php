<?php
namespace Titon\Model;

use Titon\Db\Database;
use Titon\Db\Entity;
use Titon\Db\EntityCollection;
use Titon\Db\Query;
use Titon\Db\Repository;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Country;
use Titon\Test\Stub\Model\Genre;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\Topic;
use Titon\Test\Stub\Model\User;
use Titon\Test\Stub\Model\Order;
use Titon\Test\TestCase;

/**
 * @property \Titon\Model\Model $object
 */
abstract class AbstractDbTest extends TestCase {

    protected function tearDown() {
        $this->logQueries();

        parent::tearDown();
    }

    /**
     * Extremely useful for validating the correct queries and the number of queries being ran.
     */
    public function logQueries() {
        print_r(array_map('strval', Database::registry()->getDriver('default')->getLoggedQueries()));
    }

    public function testAvg() {
        $this->loadFixtures('Orders');

        $this->assertEquals(16, Order::avg('quantity'));
    }

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

    public function testCreateStatic() {
        $this->loadFixtures('Books');

        $book = Book::create([
            'series_id' => 1,
            'name' => 'The Winds of Winter'
        ]);

        // Will only contains values set
        $this->assertEquals([
            'id' => 16,
            'series_id' => 1,
            'name' => 'The Winds of Winter'
        ], $book->toArray());

        $this->assertInstanceOf('Titon\Model\Model', $book);
    }

    public function testCreateStaticFailure() {
        $this->loadFixtures('Users');

        $user = User::create(['username' => 'batman']); // Already taken

        $this->assertEquals(null, $user);
    }

    public function testCreateWithOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = new User();
        $user->country_id = 1;
        $user->username = 'ironman';
        $user->firstName = 'Tony';
        $user->lastName = 'Stark';
        $user->password = '7NAks9193KAkjs1';
        $user->email = 'ironman@email.com';
        $user->age = 38;

        $profile = new Profile();
        $profile->lastLogin = '2012-06-24 17:30:33';

        $user->link($profile);

        $this->assertEquals(6, $user->save(['validate' => false]));

        $this->assertEquals(new User([
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
            'Profile' => new Profile([
                'id' => 6,
                'user_id' => 6,
                'lastLogin' => '2012-06-24 17:30:33',
                'currentLogin' => ''
            ])
        ]), User::select()->where('id', 6)->with('Profile')->first());
    }

    public function testCreateWithOneToOneResetsPrevious() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::select()
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
        ]), $user);

        // Change profile
        $profile = new Profile();
        $profile->lastLogin = '2012-06-24 17:30:33';

        $user->link($profile);

        $this->assertEquals(1, $user->save(['validate' => false]));

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
                'id' => 6,
                'user_id' => 1,
                'lastLogin' => '2012-06-24 17:30:33',
                'currentLogin' => ''
            ])
        ]), User::select()->with('Profile')->where('id', 1)->first());

        $this->assertEquals(new Profile([
            'id' => 4,
            'user_id' => null,
            'lastLogin' => '2012-02-15 21:22:34',
            'currentLogin' => '2013-06-06 19:11:03'
        ]), Profile::find(4));
    }

    public function testCreateWithOneToMany() {
        $this->loadFixtures(['Series', 'Books']);

        $series = new Series();
        $series->name = 'A Series Of Unfortunate Events';
        $series->linkMany([
            new Book(['name' => 'The Bad Beginning']),
            new Book(['name' => 'The Reptile Room']),
            new Book(['name' => 'The Wide Window']),
            new Book(['name' => 'The Miserable Mill']),
            new Book(['name' => 'The Austere Academy']),
            new Book(['name' => 'The Ersatz Elevator']),
            new Book(['name' => 'The Vile Village'])
        ]);

        $this->assertEquals(4, $series->save(['validate' => false]));

        $this->assertEquals(new Series([
            'id' => 4,
            'author_id' => 0,
            'name' => 'A Series Of Unfortunate Events',
            'Books' => new EntityCollection([
                new Book(['id' => 16, 'series_id' => 4, 'name' => 'The Bad Beginning', 'isbn' => '', 'released' => '']),
                new Book(['id' => 17, 'series_id' => 4, 'name' => 'The Reptile Room', 'isbn' => '', 'released' => '']),
                new Book(['id' => 18, 'series_id' => 4, 'name' => 'The Wide Window', 'isbn' => '', 'released' => '']),
                new Book(['id' => 19, 'series_id' => 4, 'name' => 'The Miserable Mill', 'isbn' => '', 'released' => '']),
                new Book(['id' => 20, 'series_id' => 4, 'name' => 'The Austere Academy', 'isbn' => '', 'released' => '']),
                new Book(['id' => 21, 'series_id' => 4, 'name' => 'The Ersatz Elevator', 'isbn' => '', 'released' => '']),
                new Book(['id' => 22, 'series_id' => 4, 'name' => 'The Vile Village', 'isbn' => '', 'released' => '']),
            ])
        ]), Series::select()->with('Books')->where('id', 4)->first());

        // Save more
        $series->linkMany([
            new Book(['name' => 'The Hostile Hospital']),
            new Book(['name' => 'The Carnivorous Carnival']),
            new Book(['name' => 'The Slippery Slope']),
            new Book(['name' => 'The Grim Grotto']),
            new Book(['name' => 'The Penultimate Peril']),
            new Book(['name' => 'The End']),
        ]);

        $this->assertEquals(4, $series->save(['validate' => false]));

        $this->assertEquals(new Series([
            'id' => 4,
            'author_id' => 0,
            'name' => 'A Series Of Unfortunate Events',
            'Books' => new EntityCollection([
                new Book(['id' => 16, 'series_id' => 4, 'name' => 'The Bad Beginning', 'isbn' => '', 'released' => '']),
                new Book(['id' => 17, 'series_id' => 4, 'name' => 'The Reptile Room', 'isbn' => '', 'released' => '']),
                new Book(['id' => 18, 'series_id' => 4, 'name' => 'The Wide Window', 'isbn' => '', 'released' => '']),
                new Book(['id' => 19, 'series_id' => 4, 'name' => 'The Miserable Mill', 'isbn' => '', 'released' => '']),
                new Book(['id' => 20, 'series_id' => 4, 'name' => 'The Austere Academy', 'isbn' => '', 'released' => '']),
                new Book(['id' => 21, 'series_id' => 4, 'name' => 'The Ersatz Elevator', 'isbn' => '', 'released' => '']),
                new Book(['id' => 22, 'series_id' => 4, 'name' => 'The Vile Village', 'isbn' => '', 'released' => '']),
                new Book(['id' => 23, 'series_id' => 4, 'name' => 'The Hostile Hospital', 'isbn' => '', 'released' => '']),
                new Book(['id' => 24, 'series_id' => 4, 'name' => 'The Carnivorous Carnival', 'isbn' => '', 'released' => '']),
                new Book(['id' => 25, 'series_id' => 4, 'name' => 'The Slippery Slope', 'isbn' => '', 'released' => '']),
                new Book(['id' => 26, 'series_id' => 4, 'name' => 'The Grim Grotto', 'isbn' => '', 'released' => '']),
                new Book(['id' => 27, 'series_id' => 4, 'name' => 'The Penultimate Peril', 'isbn' => '', 'released' => '']),
                new Book(['id' => 28, 'series_id' => 4, 'name' => 'The End', 'isbn' => '', 'released' => '']),
            ])
        ]), Series::select()->with('Books')->where('id', 4)->first());
    }

    public function testCreateWithManyToMany() {
        $this->loadFixtures(['Genres', 'Books', 'BookGenres']);

        $book = new Book();
        $book->series_id = 1;
        $book->name = 'The Winds of Winter';
        $book->linkMany([
            new Genre(['id' => 3, 'name' => 'Action-Adventure']), // Existing genre
            new Genre(['name' => 'Epic-Horror']), // New genre
        ]);

        $this->assertEquals(16, $book->save(['validate' => false]));

        $this->assertEquals(new Book([
            'id' => 16,
            'series_id' => 1,
            'name' => 'The Winds of Winter',
            'isbn' => '',
            'released' => '',
            'Genres' => new EntityCollection([
                new Genre([
                    'id' => 3,
                    'name' => 'Action-Adventure',
                    'book_count' => 8,
                    'junction' => new Entity([
                        'id' => 46,
                        'book_id' => 16,
                        'genre_id' => 3
                    ])
                ]),
                new Genre([
                    'id' => 12,
                    'name' => 'Epic-Horror',
                    'book_count' => 0,
                    'junction' => new Entity([
                        'id' => 47,
                        'book_id' => 16,
                        'genre_id' => 12
                    ])
                ])
            ])
        ]), Book::select()->with('Genres')->where('id', 16)->first());
    }

    public function testCreateWithManyToOne() {
        $this->loadFixtures(['Series', 'Books']);

        $series = Series::find(1);

        $this->assertEquals(new Series([
            'id' => 1,
            'author_id' => 1,
            'name' => 'A Song of Ice and Fire'
        ]), $series);

        // Change a value and see if it gets saved when the book does
        $series->name = 'ASOFAI';

        $book = new Book();
        $book->name = 'The Winds of Winter';
        $book->link($series);

        $this->assertEquals(16, $book->save(['validate' => false]));

        $this->assertEquals(new Book([
            'id' => 16,
            'series_id' => 1,
            'name' => 'The Winds of Winter',
            'isbn' => '',
            'released' => '',
            'Series' => new Series([
                'id' => 1,
                'author_id' => 1,
                'name' => 'ASOFAI'
            ])
        ]), Book::select()->with('Series')->where('id', 16)->first());
    }

    public function testDecrement() {
        $this->loadFixtures('Topics');

        $this->assertEquals(new Topic(['post_count' => 4]), Topic::select(['post_count'])->where('id', 1)->first());

        Topic::decrement(1, ['post_count' => 1]);

        $this->assertEquals(new Topic(['post_count' => 3]), Topic::select(['post_count'])->where('id', 1)->first());
    }

    public function testDelete() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::find(1);

        $this->assertTrue($user->exists());
        $this->assertEquals(1, $user->delete());
        $this->assertFalse($user->exists());

        $user = User::find(1);

        $this->assertFalse($user->exists());
    }

    public function testDeleteWithOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::select()
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
        ]), $user);

        $this->assertEquals(1, $user->delete());

        $user = User::find(1);

        $this->assertFalse($user->exists());

        $profile = Profile::find(4);

        $this->assertEquals(new Profile([
            'id' => 4,
            'user_id' => null,
            'lastLogin' => '2012-02-15 21:22:34',
            'currentLogin' => '2013-06-06 19:11:03'
        ]), $profile);
    }

    public function testDeleteWithOneToMany() {
        $this->loadFixtures(['Series', 'Books']);

        $series = Series::select()
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
        ]), $series);

        $this->assertEquals(1, $series->delete());

        $series = Series::find(1);

        $this->assertFalse($series->exists());

        $books = Book::select()->where('series_id', null)->orderBy('id', 'asc')->all();

        $this->assertEquals(new EntityCollection([
            new Book(['id' => 1, 'series_id' => null, 'name' => 'A Game of Thrones', 'isbn' => '0-553-10354-7', 'released' => '1996-08-02']),
            new Book(['id' => 2, 'series_id' => null, 'name' => 'A Clash of Kings', 'isbn' => '0-553-10803-4', 'released' => '1999-02-25']),
            new Book(['id' => 3, 'series_id' => null, 'name' => 'A Storm of Swords', 'isbn' => '0-553-10663-5', 'released' => '2000-11-11']),
            new Book(['id' => 4, 'series_id' => null, 'name' => 'A Feast for Crows', 'isbn' => '0-553-80150-3', 'released' => '2005-11-02']),
            new Book(['id' => 5, 'series_id' => null, 'name' => 'A Dance with Dragons', 'isbn' => '0-553-80147-3', 'released' => '2011-07-19']),
        ]), $books);
    }

    public function testDeleteWithManyToOne() {
        $this->loadFixtures(['Users', 'Countries', 'Profiles']);

        $user = User::select()
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
        ]), $user);

        $this->assertEquals(1, $user->delete());

        $user = User::find(1);

        $this->assertFalse($user->exists());

        $country = Country::find(1);

        $this->assertEquals(new Country([
            'id' => 1,
            'name' => 'United States of America',
            'iso' => 'USA'
        ]), $country);
    }

    public function testDeleteWithManyToMany() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres']);

        $book = Book::select()
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
        ]), $book);

        $this->assertEquals(1, $book->delete());

        $book = Book::find(5);

        $this->assertFalse($book->exists());

        // Related record is not deleted
        $genre = Genre::find(3);

        $this->assertEquals(new Genre([
            'id' => 3,
            'name' => 'Action-Adventure',
            'book_count' => 8
        ]), $genre);

        // Junction records are deleted
        $repo = new Repository(['table' => 'books_genres']);

        $this->assertEquals(0, $repo->select()->where('book_id', 5)->count());
    }

    public function testFetchWithOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::find(1);

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
            'modified' => null
        ]), $user);

        $profile = $user->Profile;

        $this->assertEquals(new Profile([
            'id' => 4,
            'user_id' => 1,
            'lastLogin' => '2012-02-15 21:22:34',
            'currentLogin' => '2013-06-06 19:11:03'
        ]), $profile);
    }

    public function testFetchWithOneToMany() {
        $this->loadFixtures(['Books', 'Series']);

        $series = Series::find(1);

        $this->assertEquals(new Series([
            'id' => 1,
            'author_id' => 1,
            'name' => 'A Song of Ice and Fire'
        ]), $series);

        $books = $series->Books;

        $this->assertEquals(new EntityCollection([
            new Book(['id' => 1, 'series_id' => 1, 'name' => 'A Game of Thrones', 'isbn' => '0-553-10354-7', 'released' => '1996-08-02']),
            new Book(['id' => 2, 'series_id' => 1, 'name' => 'A Clash of Kings', 'isbn' => '0-553-10803-4', 'released' => '1999-02-25']),
            new Book(['id' => 3, 'series_id' => 1, 'name' => 'A Storm of Swords', 'isbn' => '0-553-10663-5', 'released' => '2000-11-11']),
            new Book(['id' => 4, 'series_id' => 1, 'name' => 'A Feast for Crows', 'isbn' => '0-553-80150-3', 'released' => '2005-11-02']),
            new Book(['id' => 5, 'series_id' => 1, 'name' => 'A Dance with Dragons', 'isbn' => '0-553-80147-3', 'released' => '2011-07-19']),
        ]), $books);
    }

    public function testFetchWithManyToOne() {
        $this->loadFixtures(['Users', 'Countries']);

        $user = User::find(1);

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
            'modified' => null
        ]), $user);

        $country = $user->Country;

        $this->assertEquals(new Country([
            'id' => 1,
            'name' => 'United States of America',
            'iso' => 'USA'
        ]), $country);
    }

    public function testFetchWithManyToMany() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres']);

        $book = Book::find(5);

        $this->assertEquals(new Book([
            'id' => 5,
            'series_id' => 1,
            'name' => 'A Dance with Dragons',
            'isbn' => '0-553-80147-3',
            'released' => '2011-07-19'
        ]), $book);

        $genres = $book->Genres;

        $this->assertEquals(new EntityCollection([
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
        ]), $genres);
    }

    public function testFind() {
        $this->loadFixtures('Users');

        $user1 = User::find(1);
        $user2 = User::find(10);

        $this->assertInstanceOf('Titon\Model\Model', $user1);
        $this->assertInstanceOf('Titon\Model\Model', $user2);

        $this->assertTrue($user1->exists());
        $this->assertFalse($user2->exists());

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
            'modified' => null
        ]), $user1);

        $this->assertEquals([], $user2->toArray());
    }

    public function testFindBy() {
        $this->loadFixtures('Users');

        $user1 = User::findBy('username', 'miles');
        $user2 = User::findBy('username', 'ironman');

        $this->assertInstanceOf('Titon\Model\Model', $user1);
        $this->assertInstanceOf('Titon\Model\Model', $user2);

        $this->assertTrue($user1->exists());
        $this->assertFalse($user2->exists());

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
            'modified' => null
        ]), $user1);

        $this->assertEquals([], $user2->toArray());
    }

    public function testIncrement() {
        $this->loadFixtures('Topics');

        $this->assertEquals(new Topic(['post_count' => 4]), Topic::select(['post_count'])->where('id', 1)->first());

        Topic::increment(1, ['post_count' => 3]);

        $this->assertEquals(new Topic(['post_count' => 7]), Topic::select(['post_count'])->where('id', 1)->first());
    }

    public function testMax() {
        $this->loadFixtures('Orders');

        $this->assertEquals(33, Order::max('quantity'));
    }

    public function testMin() {
        $this->loadFixtures('Orders');

        $this->assertEquals(1, Order::min('quantity'));
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

    public function testSelect() {
        $query = User::select(['foo', 'bar']);

        $this->assertInstanceOf('Titon\Model\QueryBuilder', $query);
        $this->assertEquals('select', $query->getQuery()->getType());
        $this->assertEquals(['foo', 'bar'], $query->getQuery()->getFields());
    }

    public function testSum() {
        $this->loadFixtures('Orders');

        $this->assertEquals(490, Order::sum('quantity'));
    }

    public function testTotal() {
        $this->loadFixtures('Orders');

        $this->assertEquals(30, Order::total());
    }

    public function testTruncate() {
        $this->loadFixtures('Orders');

        $this->assertEquals(30, Order::total());

        Order::truncate();

        $this->assertEquals(0, Order::total());
    }

    public function testUpdate() {
        $this->loadFixtures('Users');

        $user = User::find(1);

        $this->assertEquals(new User([
            'id' => 1,
            'country_id' => 1,
            'username' => 'miles',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null
        ]), $user);

        $user->country_id = 3;
        $user->username = 'milesj';

        $this->assertEquals(1, $user->save(['validate' => false]));

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
        ]), User::find(1));
    }

    public function testUpdateStatic() {
        $this->loadFixtures('Books');

        $book = Book::update(1, ['name' => 'GoT']);

        $this->assertEquals([
            'id' => 1,
            'series_id' => 1,
            'name' => 'GoT',
            'isbn' => '0-553-10354-7',
            'released' => '1996-08-02'
        ], $book->toArray());

        $this->assertInstanceOf('Titon\Model\Model', $book);
    }

    public function testUpdateStaticFailure() {
        $this->loadFixtures('Users');

        $user = User::update(1, ['username' => 'batman']); // Already taken

        $this->assertEquals(null, $user);
    }

    public function testUpdateStaticInvalidID() {
        $this->loadFixtures('Books');

        $book = Book::update(666, ['name' => 'Foobar']);

        $this->assertEquals(null, $book);
    }

    public function testUpdateWithOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = User::find(1);

        $this->assertEquals(new User([
            'id' => 1,
            'country_id' => 1,
            'username' => 'miles',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => null
        ]), $user);

        $profile = $user->Profile;

        $this->assertEquals(new Profile([
            'id' => 4,
            'user_id' => 1,
            'lastLogin' => '2012-02-15 21:22:34',
            'currentLogin' => '2013-06-06 19:11:03'
        ]), $profile);

        // Update records
        $time = time();

        $user->country_id = 3;
        $user->username = 'milesj';

        $profile->currentLogin = $time;
        $user->link($profile);

        $this->assertEquals(1, $user->save(['validate' => false]));

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
            'modified' => null,
            'Profile' => new Profile([
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-02-15 21:22:34',
                'currentLogin' => date('Y-m-d H:i:s', $time)
            ])
        ]), User::select()->with('Profile')->where('id', 1)->first());
    }

}