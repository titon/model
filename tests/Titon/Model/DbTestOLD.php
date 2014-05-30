<?php

namespace Titon\Model;


class DbTest {


    /**
     * Test row inserting with one to one relation data.
     */
    public function testCreateWithOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = new User();
        $data = [
            'country_id' => 1,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'age' => 38,
            'Profile' => [
                'lastLogin' => '2012-06-24 17:30:33'
            ]
        ];

        $this->assertEquals(6, $user->create($data));

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
        ], $user->data);

        // Should throw errors for invalid array structure
        unset($data['id'], $data['Profile']);

        $data['Profile'] = [
            ['lastLogin' => '2012-06-24 17:30:33'] // Nested array
        ];

        try {
            $this->assertEquals(7, $user->create($data));
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test row inserting with one to many relation data.
     */
    public function testCreateWithOneToMany() {
        $this->loadFixtures(['Series', 'Books']);

        $series = new Series();
        $books = [
            ['name' => 'The Bad Beginning'],
            ['name' => 'The Reptile Room'],
            ['name' => 'The Wide Window'],
            ['name' => 'The Miserable Mill'],
            ['name' => 'The Austere Academy'],
            ['name' => 'The Ersatz Elevator'],
            ['name' => 'The Vile Village'],
            ['name' => 'The Hostile Hospital'],
            ['name' => 'The Carnivorous Carnival'],
            ['name' => 'The Slippery Slope'],
            ['name' => 'The Grim Grotto'],
            ['name' => 'The Penultimate Peril'],
            ['name' => 'The End'],
        ];

        $data = [
            'name' => 'A Series Of Unfortunate Events',
            'Books' => $books
        ];

        $this->assertEquals(4, $series->create($data));

        $this->assertEquals([
            'id' => 4,
            'author_id' => '',
            'name' => 'A Series Of Unfortunate Events',
            'Books' => [
                ['id' => 16, 'series_id' => 4, 'name' => 'The Bad Beginning', 'isbn' => '', 'released' => ''],
                ['id' => 17, 'series_id' => 4, 'name' => 'The Reptile Room', 'isbn' => '', 'released' => ''],
                ['id' => 18, 'series_id' => 4, 'name' => 'The Wide Window', 'isbn' => '', 'released' => ''],
                ['id' => 19, 'series_id' => 4, 'name' => 'The Miserable Mill', 'isbn' => '', 'released' => ''],
                ['id' => 20, 'series_id' => 4, 'name' => 'The Austere Academy', 'isbn' => '', 'released' => ''],
                ['id' => 21, 'series_id' => 4, 'name' => 'The Ersatz Elevator', 'isbn' => '', 'released' => ''],
                ['id' => 22, 'series_id' => 4, 'name' => 'The Vile Village', 'isbn' => '', 'released' => ''],
                ['id' => 23, 'series_id' => 4, 'name' => 'The Hostile Hospital', 'isbn' => '', 'released' => ''],
                ['id' => 24, 'series_id' => 4, 'name' => 'The Carnivorous Carnival', 'isbn' => '', 'released' => ''],
                ['id' => 25, 'series_id' => 4, 'name' => 'The Slippery Slope', 'isbn' => '', 'released' => ''],
                ['id' => 26, 'series_id' => 4, 'name' => 'The Grim Grotto', 'isbn' => '', 'released' => ''],
                ['id' => 27, 'series_id' => 4, 'name' => 'The Penultimate Peril', 'isbn' => '', 'released' => ''],
                ['id' => 28, 'series_id' => 4, 'name' => 'The End', 'isbn' => '', 'released' => ''],
            ]
        ], $series->data);

        // Should throw errors for invalid array structure
        unset($data['id'], $data['Books']);

        $data['Books'] = [
            'name' => 'The Bad Beginning'
        ]; // Non numeric array

        try {
            $this->assertEquals(4, $series->create($data));
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test row inserting with many to many relation data.
     */
    public function testCreateWithManyToMany() {
        $this->loadFixtures(['Genres', 'Books', 'BookGenres']);

        $book = new Book();
        $data = [
            'series_id' => 1,
            'name' => 'The Winds of Winter',
            'Genres' => [
                ['id' => 3, 'name' => 'Action-Adventure'], // Existing genre
                ['name' => 'Epic-Horror'], // New genre
                ['genre_id' => 8] // Existing genre by ID
            ]
        ];

        $this->assertEquals(16, $book->create($data));

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
                    'book_count' => '',
                    'Junction' => [
                        'book_id' => 16,
                        'genre_id' => 3,
                        'id' => 46
                    ]
                ],
                [
                    'id' => 12,
                    'name' => 'Epic-Horror',
                    'book_count' => '',
                    'Junction' => [
                        'book_id' => 16,
                        'genre_id' => 12,
                        'id' => 47
                    ]
                ],
                [
                    // Data isn't set when using foreign keys
                    'Junction' => [
                        'book_id' => 16,
                        'genre_id' => 8,
                        'id' => 48
                    ]
                ]
            ]
        ], $book->data);
    }

    /**
     * Test that one-to-one relation dependents are deleted.
     */
    public function testDeleteCascadeOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = new User();

        $this->assertTrue($user->exists(2));
        $this->assertTrue($user->Profile->exists(5));

        $user->delete(2, true);

        $this->assertFalse($user->exists(2));
        $this->assertFalse($user->Profile->exists(5));
    }

    /**
     * Test that one-to-many relation dependents are deleted.
     */
    public function testDeleteCascadeOneToMany() {
        $this->loadFixtures(['Series', 'Books', 'BookGenres', 'Genres']);

        $series = new Series();

        $this->assertTrue($series->exists(1));
        $this->assertEquals(new EntityCollection([
            new Entity(['id' => 1, 'name' => 'A Game of Thrones']),
            new Entity(['id' => 2, 'name' => 'A Clash of Kings']),
            new Entity(['id' => 3, 'name' => 'A Storm of Swords']),
            new Entity(['id' => 4, 'name' => 'A Feast for Crows']),
            new Entity(['id' => 5, 'name' => 'A Dance with Dragons']),
        ]), $series->Books->select('id', 'name')->where('series_id', 1)->orderBy('id', 'asc')->all());

        $series->delete(1, true);

        $this->assertFalse($series->exists(1));
        $this->assertEquals(new EntityCollection(), $series->Books->select('id', 'name')->where('series_id', 1)->all());
    }

    /**
     * Test that many-to-many relation dependents are deleted.
     */
    public function testDeleteCascadeManyToMany() {
        $this->loadFixtures(['Books', 'BookGenres', 'Genres']);

        $book = new Book();
        $bookGenres = new BookGenre();

        $this->assertTrue($book->exists(5));

        // Trigger lazy-loaded queries
        $results = $book->select('id', 'name')->where('id', 5)->with('Genres')->first();
        $results->Genres;

        $this->assertEquals(new Entity([
            'id' => 5,
            'name' => 'A Dance with Dragons',
            'Genres' => new EntityCollection([
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
                ])
            ])
        ]), $results);

        $book->delete(5, true);

        $this->assertFalse($book->exists(5));
        $this->assertEquals(null, $book->select()->where('id', 5)->with('Genres')->first());
        $this->assertEquals(null, $bookGenres->select()->where('book_id', 5)->first());

        // The related records don't get deleted
        // Only the junction records should be
        $this->assertEquals(new EntityCollection([
            new Entity(['id' => 3, 'name' => 'Action-Adventure']),
            new Entity(['id' => 5, 'name' => 'Horror']),
            new Entity(['id' => 8, 'name' => 'Fantasy']),
        ]), $book->Genres->select('id', 'name')->where('id', [3, 5, 8])->all());
    }

    /**
     * Test that deep relations are also deleted.
     */
    public function testDeleteCascadeDeepRelations() {
        $this->loadFixtures(['Series', 'Books', 'BookGenres', 'Genres']);

        $series = new Series();
        $bookGenres = new BookGenre();

        $this->assertTrue($series->exists(3));

        $this->assertEquals(new EntityCollection([
            new Entity(['id' => 13, 'name' => 'The Fellowship of the Ring']),
            new Entity(['id' => 14, 'name' => 'The Two Towers']),
            new Entity(['id' => 15, 'name' => 'The Return of the King']),
        ]), $series->Books->select('id', 'name')->where('series_id', 3)->orderBy('id', 'asc')->all());

        // Trigger lazy-loaded queries
        $results = $series->Books->select('id', 'name')->where('id', 14)->with('Genres')->first();
        $results->Genres;

        $this->assertEquals(new Entity([
            'id' => 14,
            'name' => 'The Two Towers',
            'Genres' => new EntityCollection([
                new Entity([
                    'id' => 3,
                    'name' => 'Action-Adventure',
                    'book_count' => 8,
                    'Junction' => new Entity([
                        'id' => 41,
                        'book_id' => 14,
                        'genre_id' => 3
                    ])
                ]),
                new Entity([
                    'id' => 6,
                    'name' => 'Thriller',
                    'book_count' => 3,
                    'Junction' => new Entity([
                        'id' => 42,
                        'book_id' => 14,
                        'genre_id' => 6
                    ])
                ]),
                new Entity([
                    'id' => 8,
                    'name' => 'Fantasy',
                    'book_count' => 15,
                    'Junction' => new Entity([
                        'id' => 40,
                        'book_id' => 14,
                        'genre_id' => 8
                    ])
                ])
            ])
        ]), $results);

        $series->delete(3, true);

        $this->assertFalse($series->exists(3));
        $this->assertEquals(new EntityCollection(), $series->Books->select('id', 'name')->where('series_id', 3)->all());
        $this->assertEquals(null, $bookGenres->select()->where('book_id', 14)->first());
    }

    /**
     * Test that dependents aren't deleted if cascade is false.
     */
    public function testDeleteNoCascade() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = new User();

        $this->assertTrue($user->exists(2));
        $this->assertTrue($user->Profile->exists(5));

        $user->delete(2, false);

        $this->assertFalse($user->exists(2));
        $this->assertTrue($user->Profile->exists(5));
    }


    /**
     * Test fetching of rows while including many to one (belongs to) relations.
     */
    public function testFetchWithManyToOne() {
        $this->loadFixtures(['Books', 'Series']);

        $book = new Book();

        // Single
        $this->assertEquals(new Entity([
            'id' => 5,
            'series_id' => 1,
            'name' => 'A Dance with Dragons',
            'isbn' => '0-553-80147-3',
            'released' => '2011-07-19',
            'Series' => new Entity([
                'id' => 1,
                'author_id' => 1,
                'name' => 'A Song of Ice and Fire'
            ])
        ]), $book->select()->where('id', 5)->with('Series')->orderBy('id', 'asc')->first(['eager' => true]));

        // Multiple
        $this->assertEquals(new EntityCollection([
            new Entity([
                'id' => 13,
                'series_id' => 3,
                'name' => 'The Fellowship of the Ring',
                'isbn' => '',
                'released' => '1954-07-24',
                'Series' => new Entity([
                    'id' => 3,
                    'author_id' => 3,
                    'name' => 'The Lord of the Rings'
                ])
            ]),
            new Entity([
                'id' => 14,
                'series_id' => 3,
                'name' => 'The Two Towers',
                'isbn' => '',
                'released' => '1954-11-11',
                'Series' => new Entity([
                    'id' => 3,
                    'author_id' => 3,
                    'name' => 'The Lord of the Rings'
                ])
            ]),
            new Entity([
                'id' => 15,
                'series_id' => 3,
                'name' => 'The Return of the King',
                'isbn' => '',
                'released' => '1955-10-25',
                'Series' => new Entity([
                    'id' => 3,
                    'author_id' => 3,
                    'name' => 'The Lord of the Rings'
                ])
            ]),
        ]), $book->select()->where('series_id', 3)->with('Series')->orderBy('id', 'asc')->all(['eager' => true]));
    }

    /**
     * Test fetching of rows while including many to many (has and belongs to many) relations.
     */
    public function testFetchWithManyToMany() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres']);

        $book = new Book();

        // Single
        $actual = $book->select()->where('id', 5)->with('Genres')->first(['eager' => true]);

        $this->assertEquals(new Entity([
            'id' => 5,
            'series_id' => 1,
            'name' => 'A Dance with Dragons',
            'isbn' => '0-553-80147-3',
            'released' => '2011-07-19',
            'Genres' => new EntityCollection([
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
            ])
        ]), $actual);

        // Multiple
        $actual = $book->select()->where('series_id', 3)->with('Genres')->orderBy('id', 'asc')->all(['eager' => true]);

        $this->assertEquals(new EntityCollection([
            new Entity([
                'id' => 13,
                'series_id' => 3,
                'name' => 'The Fellowship of the Ring',
                'isbn' => '',
                'released' => '1954-07-24',
                'Genres' => new EntityCollection([
                    new Entity([
                        'id' => 3,
                        'name' => 'Action-Adventure',
                        'book_count' => 8,
                        'Junction' => new Entity([
                            'id' => 38,
                            'book_id' => 13,
                            'genre_id' => 3
                        ])
                    ]),
                    new Entity([
                        'id' => 6,
                        'name' => 'Thriller',
                        'book_count' => 3,
                        'Junction' => new Entity([
                            'id' => 39,
                            'book_id' => 13,
                            'genre_id' => 6
                        ])
                    ]),
                    new Entity([
                        'id' => 8,
                        'name' => 'Fantasy',
                        'book_count' => 15,
                        'Junction' => new Entity([
                            'id' => 37,
                            'book_id' => 13,
                            'genre_id' => 8
                        ])
                    ]),
                ])
            ]),
            new Entity([
                'id' => 14,
                'series_id' => 3,
                'name' => 'The Two Towers',
                'isbn' => '',
                'released' => '1954-11-11',
                'Genres' => new EntityCollection([
                    new Entity([
                        'id' => 3,
                        'name' => 'Action-Adventure',
                        'book_count' => 8,
                        'Junction' => new Entity([
                            'id' => 41,
                            'book_id' => 14,
                            'genre_id' => 3
                        ])
                    ]),
                    new Entity([
                        'id' => 6,
                        'name' => 'Thriller',
                        'book_count' => 3,
                        'Junction' => new Entity([
                            'id' => 42,
                            'book_id' => 14,
                            'genre_id' => 6
                        ])
                    ]),
                    new Entity([
                        'id' => 8,
                        'name' => 'Fantasy',
                        'book_count' => 15,
                        'Junction' => new Entity([
                            'id' => 40,
                            'book_id' => 14,
                            'genre_id' => 8
                        ])
                    ]),
                ])
            ]),
            new Entity([
                'id' => 15,
                'series_id' => 3,
                'name' => 'The Return of the King',
                'isbn' => '',
                'released' => '1955-10-25',
                'Genres' => new EntityCollection([
                    new Entity([
                        'id' => 3,
                        'name' => 'Action-Adventure',
                        'book_count' => 8,
                        'Junction' => new Entity([
                            'id' => 44,
                            'book_id' => 15,
                            'genre_id' => 3
                        ])
                    ]),
                    new Entity([
                        'id' => 6,
                        'name' => 'Thriller',
                        'book_count' => 3,
                        'Junction' => new Entity([
                            'id' => 45,
                            'book_id' => 15,
                            'genre_id' => 6
                        ])
                    ]),
                    new Entity([
                        'id' => 8,
                        'name' => 'Fantasy',
                        'book_count' => 15,
                        'Junction' => new Entity([
                            'id' => 43,
                            'book_id' => 15,
                            'genre_id' => 8
                        ])
                    ]),
                ])
            ]),
        ]), $actual);
    }

    /**
     * Test fetching of rows while including deeply nested relations.
     */
    public function testFetchWithComplexRelations() {
        $this->loadFixtures(['Books', 'Series', 'Authors', 'Genres', 'BookGenres']);

        $series = new Series();

        // Single
        $actual = $series->select()
            ->where('id', 1)
            ->with('Author')
            ->with('Books', function(Query $query) {
                $query->with('Genres');
            })
            ->first(['eager' => true]);

        $this->assertEquals(new Entity([
            'id' => 1,
            'author_id' => 1,
            'name' => 'A Song of Ice and Fire',
            'Author' => new Entity([
                'id' => 1,
                'name' => 'George R. R. Martin'
            ]),
            'Books' => new EntityCollection([
                new Entity([
                    'id' => 1,
                    'series_id' => 1,
                    'name' => 'A Game of Thrones',
                    'isbn' => '0-553-10354-7',
                    'released' => '1996-08-02',
                    'Genres' => new EntityCollection([
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
                    ])
                ]),
                new Entity([
                    'id' => 2,
                    'series_id' => 1,
                    'name' => 'A Clash of Kings',
                    'isbn' => '0-553-10803-4',
                    'released' => '1999-02-25',
                    'Genres' => new EntityCollection([
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
                    ])
                ]),
                new Entity([
                    'id' => 3,
                    'series_id' => 1,
                    'name' => 'A Storm of Swords',
                    'isbn' => '0-553-10663-5',
                    'released' => '2000-11-11',
                    'Genres' => new EntityCollection([
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
                    ])
                ]),
                new Entity([
                    'id' => 4,
                    'series_id' => 1,
                    'name' => 'A Feast for Crows',
                    'isbn' => '0-553-80150-3',
                    'released' => '2005-11-02',
                    'Genres' => new EntityCollection([
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
                    ])
                ]),
                new Entity([
                    'id' => 5,
                    'series_id' => 1,
                    'name' => 'A Dance with Dragons',
                    'isbn' => '0-553-80147-3',
                    'released' => '2011-07-19',
                    'Genres' => new EntityCollection([
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
                    ])
                ]),
            ])
        ]), $actual);
    }

    /**
     * Test database record updating with one to one relations.
     */
    public function testUpdateWithOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = new User();
        $data = [
            'country_id' => 3,
            'username' => 'milesj',
            'Profile' => [
                'id' => 4,
                'lastLogin' => '2012-06-24 17:30:33'
            ]
        ];

        $this->assertEquals(1, $user->update(1, $data));

        $this->assertEquals([
            'id' => 1,
            'country_id' => 3,
            'username' => 'milesj',
            'password' => '',
            'email' => '',
            'firstName' => '',
            'lastName' => '',
            'age' => '',
            'created' => '',
            'modified' => '',
            'Profile' => [
                'id' => 4,
                'user_id' => 1,
                'lastLogin' => '2012-06-24 17:30:33',
                'currentLogin' => ''
            ]
        ], $user->data);

        // Should throw errors for invalid array structure
        unset($data['id'], $data['Profile']);

        $data['Profile'] = [
            ['lastLogin' => '2012-06-24 17:30:33'] // Nested array
        ];

        try {
            $this->assertEquals(1, $user->update(1, $data));
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Will upsert if no one-to-one ID is present
        $data = [
            'country_id' => 3,
            'username' => 'miles',
            'Profile' => [
                'currentLogin' => '2012-06-24 17:30:33'
            ]
        ];

        $this->assertEquals(1, $user->update(1, $data));

        $this->assertEquals([
            'id' => 1,
            'country_id' => 3,
            'username' => 'miles',
            'password' => '',
            'email' => '',
            'firstName' => '',
            'lastName' => '',
            'age' => '',
            'created' => '',
            'modified' => '',
            'Profile' => [
                'id' => 6,
                'user_id' => 1,
                'lastLogin' => '',
                'currentLogin' => '2012-06-24 17:30:33',
            ]
        ], $user->data);
    }

    /**
     * Test database record updating with one to many relations.
     */
    public function testUpdateWithOneToMany() {
        $this->loadFixtures(['Books', 'Series']);

        $series = new Series();
        $data = [
            'author_id' => 3,
            'name' => 'The Lord of the Rings (Updated)',
            'Books' => [
                ['id' => 13, 'series_id' => 3, 'name' => 'The Fellowship of the Ring (Updated)'],
                ['id' => 14, 'series_id' => 3, 'name' => 'The Two Towers (Updated)'],
                ['id' => 15, 'series_id' => 3, 'name' => 'The Return of the King (Updated)'],
            ]
        ];

        $this->assertEquals(1, $series->update(3, $data));

        $this->assertEquals([
            'id' => 3,
            'author_id' => 3,
            'name' => 'The Lord of the Rings (Updated)',
            'Books' => [
                ['id' => 13, 'series_id' => 3, 'name' => 'The Fellowship of the Ring (Updated)', 'isbn' => '', 'released' => ''],
                ['id' => 14, 'series_id' => 3, 'name' => 'The Two Towers (Updated)', 'isbn' => '', 'released' => ''],
                ['id' => 15, 'series_id' => 3, 'name' => 'The Return of the King (Updated)', 'isbn' => '', 'released' => ''],
            ]
        ], $series->data);

        // Should throw errors for invalid array structure
        unset($data['Books']);

        $data['Books'] = [
            'name' => 'The Bad Beginning'
        ]; // Non numeric array

        try {
            $series->update(3, $data);
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test database record updating with many to many relations.
     */
    public function testUpdateWithManyToMany() {
        $this->loadFixtures(['Genres', 'Books', 'BookGenres']);

        $book = new Book();
        $data = [
            'series_id' => 1,
            'name' => 'A Dance with Dragons (Updated)',
            'Genres' => [
                ['id' => 3, 'name' => 'Action-Adventure'], // Existing genre
                ['name' => 'Epic-Horror'], // New genre
                ['genre_id' => 8] // Existing genre by ID
            ]
        ];

        $this->assertEquals(1, $book->update(5, $data));

        $this->assertEquals([
            'id' => 5,
            'series_id' => 1,
            'name' => 'A Dance with Dragons (Updated)',
            'isbn' => '',
            'released' => '',
            'Genres' => [
                [
                    'id' => 3,
                    'name' => 'Action-Adventure',
                    'book_count' => '',
                    'Junction' => [
                        'id' => 14,
                        'book_id' => 5,
                        'genre_id' => 3
                    ]
                ], [
                    'id' => 12,
                    'name' => 'Epic-Horror',
                    'book_count' => '',
                    'Junction' => [
                        'book_id' => 5,
                        'genre_id' => 12,
                        'id' => 46
                    ]
                ], [
                    // Data isn't set when using foreign keys
                    'Junction' => [
                        'id' => 13,
                        'book_id' => 5,
                        'genre_id' => 8
                    ]
                ]
            ]
        ], $book->data);

        // Should throw errors for invalid array structure
        unset($data['Genres']);

        $data['Genres'] = [
            'id' => 3,
            'name' => 'Action-Adventure'
        ]; // Non numeric array

        try {
            $this->assertTrue($book->update(5, $data));
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Try again with another structure
        unset($data['Genres']);

        $data['Genres'] = [
            'Fantasy', 'Horror'
        ]; // Non array value

        try {
            $this->assertTrue($book->update(5, $data));
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test upserting for one-to-one relations.
     */
    public function testUpsertOneToOne() {
        $this->loadFixtures(['Users', 'Profiles']);

        $user = new User();
        $time = time();

        // Update
        $this->assertEquals(new Entity([
            'id' => 4,
            'user_id' => 1,
            'lastLogin' => '2012-02-15 21:22:34',
            'currentLogin' => '2013-06-06 19:11:03'
        ]), $user->Profile->select()->where('id', 4)->first());

        $this->assertEquals(1, $user->upsertRelations(1, [
            'Profile' => [
                'id' => 4,
                'lastLogin' => $time
            ]
        ]));

        $this->assertEquals(new Entity([
            'id' => 4,
            'user_id' => 1,
            'lastLogin' => date('Y-m-d H:i:s', $time),
            'currentLogin' => '2013-06-06 19:11:03'
        ]), $user->Profile->select()->where('id', 4)->first());

        // Create
        $this->assertFalse($user->Profile->exists(6));

        $this->assertEquals(1, $user->upsertRelations(1, [
            'Profile' => [
                'lastLogin' => $time
            ]
        ]));

        $this->assertEquals(new Entity([
            'id' => 6,
            'user_id' => 1,
            'lastLogin' => date('Y-m-d H:i:s', $time),
            'currentLogin' => null
        ]), $user->Profile->select()->where('id', 6)->first());
    }

    /**
     * Test upserting for one-to-many relations.
     */
    public function testUpsertOneToMany() {
        $this->loadFixtures(['Series', 'Books']);

        $series = new Series();

        // Trigger lazy-loaded queries
        $results = $series->select()->where('id', 1)->with('Books')->first();
        $results->Books;

        $this->assertEquals(new Entity([
            'id' => 1,
            'author_id' => 1,
            'name' => 'A Song of Ice and Fire',
            'Books' => new EntityCollection([
                new Entity(['id' => 1, 'series_id' => 1, 'name' => 'A Game of Thrones', 'isbn' => '0-553-10354-7', 'released' => '1996-08-02']),
                new Entity(['id' => 2, 'series_id' => 1, 'name' => 'A Clash of Kings', 'isbn' => '0-553-10803-4', 'released' => '1999-02-25']),
                new Entity(['id' => 3, 'series_id' => 1, 'name' => 'A Storm of Swords', 'isbn' => '0-553-10663-5', 'released' => '2000-11-11']),
                new Entity(['id' => 4, 'series_id' => 1, 'name' => 'A Feast for Crows', 'isbn' => '0-553-80150-3', 'released' => '2005-11-02']),
                new Entity(['id' => 5, 'series_id' => 1, 'name' => 'A Dance with Dragons', 'isbn' => '0-553-80147-3', 'released' => '2011-07-19'])
            ])
        ]), $results);

        $this->assertEquals(3, $series->upsertRelations(1, [
            'Books' => [
                ['id' => 1, 'name' => 'A Game of Thrones (Updated)'], // Updated
                ['name' => 'The Winds of Winter'], // Created
                ['id' => 125, 'name' => 'A Dream of Spring'] // Created because of invalid ID
            ]
        ]));

        // Trigger lazy-loaded queries
        $results = $series->select()->where('id', 1)->with('Books')->first();
        $results->Books;

        $this->assertEquals(new Entity([
            'id' => 1,
            'author_id' => 1,
            'name' => 'A Song of Ice and Fire',
            'Books' => new EntityCollection([
                new Entity(['id' => 1, 'series_id' => 1, 'name' => 'A Game of Thrones (Updated)', 'isbn' => '0-553-10354-7', 'released' => '1996-08-02']),
                new Entity(['id' => 2, 'series_id' => 1, 'name' => 'A Clash of Kings', 'isbn' => '0-553-10803-4', 'released' => '1999-02-25']),
                new Entity(['id' => 3, 'series_id' => 1, 'name' => 'A Storm of Swords', 'isbn' => '0-553-10663-5', 'released' => '2000-11-11']),
                new Entity(['id' => 4, 'series_id' => 1, 'name' => 'A Feast for Crows', 'isbn' => '0-553-80150-3', 'released' => '2005-11-02']),
                new Entity(['id' => 5, 'series_id' => 1, 'name' => 'A Dance with Dragons', 'isbn' => '0-553-80147-3', 'released' => '2011-07-19']),
                new Entity(['id' => 16, 'series_id' => 1, 'name' => 'The Winds of Winter', 'isbn' => '', 'released' => '']),
                new Entity(['id' => 17, 'series_id' => 1, 'name' => 'A Dream of Spring', 'isbn' => '', 'released' => ''])
            ])
        ]), $results);
    }

    /**
     * Test upserting for many-to-many relations.
     */
    public function testUpsertWithManyToMany() {
        $this->loadFixtures(['Genres', 'Books', 'BookGenres']);

        $book = new Book();

        // Trigger lazy-loaded queries
        $results = $book->select()->where('id', 10)->with('Genres')->first();
        $results->Genres;

        $this->assertEquals(new Entity([
            'id' => 10,
            'series_id' => 2,
            'name' => 'Harry Potter and the Order of the Phoenix',
            'isbn' => '0-7475-5100-6',
            'released' => '2003-06-21',
            'Genres' => new EntityCollection([
                new Entity([
                    'id' => 2,
                    'name' => 'Adventure',
                    'book_count' => 7,
                    'Junction' => new Entity([
                        'id' => 29,
                        'book_id' => 10,
                        'genre_id' => 2
                    ])
                ]),
                new Entity([
                    'id' => 7,
                    'name' => 'Mystery',
                    'book_count' => 7,
                    'Junction' => new Entity([
                        'id' => 30,
                        'book_id' => 10,
                        'genre_id' => 7
                    ])
                ]),
                new Entity([
                    'id' => 8,
                    'name' => 'Fantasy',
                    'book_count' => 15,
                    'Junction' => new Entity([
                        'id' => 28,
                        'book_id' => 10,
                        'genre_id' => 8
                    ])
                ])
            ])
        ]), $results);

        $this->assertEquals(4, $book->upsertRelations(10, [
            'Genres' => [
                ['id' => 2, 'name' => 'Adventure (Updated)'], // Updated
                ['name' => 'Magic'], // Created
                ['id' => 125, 'name' => 'Wizardry'], // Created because of invalid ID
                ['genre_id' => 8, 'name' => 'Fantasy (Updated)'] // Updated because of direct foreign key
            ]
        ]));

        // Trigger lazy-loaded queries
        $results = $book->select()->where('id', 10)->with('Genres', function(Query $query) {
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
    public function testWith() {
        // Missing relation
        try {
            $this->object->with('Foobar', function() {});
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Not a query
        try {
            $this->object->with('Profile', []);
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $this->object->with('Profile', function(Query $query, Relation $relation) {
            $query->where($relation->getRelatedForeignKey(), 1);
        });

        $queries = $this->object->getRelationQueries();

        $this->assertInstanceOf('Titon\Db\Query', $queries['Profile']);

        // Test exceptions
        try {
            $query = new Query(Query::DELETE, new User());
            $query->with('Profile', function() {

            });

            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Multiple relations
        $this->object->with(['Profile', 'Country']);
        $this->assertEquals(['Profile', 'Country'], array_keys($this->object->getRelationQueries()));

        // Test custom query
        try {
            $query = new Query(Query::SELECT, new User());
            $query->with('Profile', new Query(Query::DELETE, new User()));

            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $query = new Query(Query::SELECT, new User());
        $query->with('Profile', new Query(Query::SELECT, new User()));
    }

    /**
     * Test table relation management.
     */
    public function testAddHasRelations() {
        $stub = new RepositoryStub();

        $this->assertFalse($stub->hasRelation('User'));

        $stub->hasOne('User', 'Titon\Test\Stub\Repository\User', 'user_id');
        $this->assertTrue($stub->hasRelation('User'));

        $this->assertInstanceOf('Titon\Db\Repository', $stub->User);
        $this->assertInstanceOf('Titon\Db\Repository', $stub->getObject('User'));
    }

    public function testBelongsTo() {
        $this->object->belongsTo('Country', 'Titon\Test\Stub\Repository\Country', 'country_id');

        $expected = new ManyToOne('Country', 'Titon\Test\Stub\Repository\Country');
        $expected->setForeignKey('country_id');
        $expected->setRepository($this->object);

        $this->assertEquals($expected, $this->object->getRelation('Country'));
    }

    public function testBelongsToMany() {
        $this->object->belongsToMany('Groups', 'Titon\Test\Stub\Repository\Group', 'Titon\Test\Stub\Repository\UserGroups', 'user_id', 'group_id');

        $expected = new ManyToMany('Groups', 'Titon\Test\Stub\Repository\Group');
        $expected->setJunctionClass('Titon\Test\Stub\Repository\UserGroups');
        $expected->setForeignKey('user_id');
        $expected->setRelatedForeignKey('group_id');
        $expected->setRepository($this->object);

        $this->assertEquals($expected, $this->object->getRelation('Groups'));
    }

    /**
     * Test relation fetching.
     */
    public function testGetRelations() {
        try {
            $this->object->getRelation('Foobar');
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $this->assertInstanceOf('Titon\Db\Relation', $this->object->getRelation('Profile'));

        $expected = [
            'Profile' => $this->object->getRelation('Profile'),
            'Country' => $this->object->getRelation('Country')
        ];

        $this->assertEquals($expected, $this->object->getRelations());

        unset($expected['Profile']);

        $this->assertEquals($expected, $this->object->getRelations(Relation::MANY_TO_ONE));
    }

    public function testHasOne() {
        $this->object->hasOne('Profile', 'Titon\Test\Stub\Repository\Profile', 'user_id');

        $expected = new OneToOne('Profile', 'Titon\Test\Stub\Repository\Profile');
        $expected->setRelatedForeignKey('user_id');
        $expected->setRepository($this->object);

        $this->assertEquals($expected, $this->object->getRelation('Profile'));
    }

    public function testHasMany() {
        $this->object->hasMany('Posts', 'Titon\Test\Stub\Repository\Post', 'user_id');

        $expected = new OneToMany('Posts', 'Titon\Test\Stub\Repository\Post');
        $expected->setRelatedForeignKey('user_id');
        $expected->setRepository($this->object);

        $this->assertEquals($expected, $this->object->getRelation('Posts'));
    }

}