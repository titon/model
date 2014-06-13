<?php
namespace Titon\Model\Behavior;

use Titon\Db\Query;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Genre;
use Titon\Test\Stub\Model\Post;
use Titon\Test\Stub\Model\Topic;
use Titon\Test\TestCase;

/**
 * @property \Titon\Model\Behavior\CounterBehavior $object
 */
class CounterBehaviorTest extends TestCase {

    protected function setUp() {
        parent::setUp();

        $this->object = new CounterBehavior();
    }

    public function testTrack() {
        $scope = function() {};

        $book = new Book();
        $book->addBehavior($this->object);

        $this->object->track('Genres', 'book_count', $scope);

        $this->assertEquals([
            'Genres' => [
                'field' => 'book_count',
                'scope' => $scope,
            ]
        ], $this->object->getCounters());
    }

    public function testOnUpsertManyToMany() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres']);

        $genre = Genre::find(3);
        $this->assertEquals(8, $genre->book_count);

        // Create new record and increase to 9
        $book = new Book();
        $book->addBehavior($this->object->track('Genres', 'book_count'));
        $book->series_id = 1;
        $book->name = 'The Winds of Winter';
        $book->link($genre);
        $book->save(['validate' => false]);

        $genre = Genre::find(3);
        $this->assertEquals(9, $genre->book_count);

        // Update a record and add to increase to 10
        $book = Book::find(12);
        $book->addBehavior($this->object->track('Genres', 'book_count'));
        $book->released = time();
        $book->link($genre);
        $book->save(['validate' => false]);

        $genre = Genre::find(3);
        $this->assertEquals(10, $genre->book_count);
    }

    public function testOnDeleteManyToMany() {
        $this->loadFixtures(['Books', 'Genres', 'BookGenres']);

        $genre = Genre::find(8);
        $this->assertEquals(15, $genre->book_count);

        // Delete a record to go down to 14
        $book = Book::find(1);
        $book->addBehavior($this->object->track('Genres', 'book_count'));
        $book->delete();

        $genre = Genre::find(8);
        $this->assertEquals(14, $genre->book_count);

        // Delete multiple records
        $book = new Book();
        $book->addBehavior($this->object->track('Genres', 'book_count'));
        $book->query(Query::DELETE)->where('series_id', 1)->save();

        $genre = Genre::find(8);
        $this->assertEquals(10, $genre->book_count);
    }

    public function testOnCreateManyToOne() {
        $this->loadFixtures(['Topics', 'Posts']);

        $topic = Topic::find(1);
        $this->assertEquals(4, $topic->post_count);

        // Create an inactive record, count shouldn't change
        $post = new Post();
        $post->addBehavior($this->object->track('Topic', 'post_count', function(Query $query) {
            $query->where('active', 1);
        }));
        $post->topic_id = 1;
        $post->active = 0;
        $post->content = 'Inactive';
        $post->save(['validate' => false]);

        $topic = Topic::find(1);
        $this->assertEquals(4, $topic->post_count);

        // Create an active record
        $post = new Post();
        $post->addBehavior($this->object->track('Topic', 'post_count', function(Query $query) {
            $query->where('active', 1);
        }));
        $post->topic_id = 1;
        $post->active = 1;
        $post->content = 'Active';
        $post->save(['validate' => false]);

        $topic = Topic::find(1);
        $this->assertEquals(5, $topic->post_count);
    }

    public function testOnUpdateManyToOne() {
        $this->loadFixtures(['Topics', 'Posts']);

        $topic = Topic::find(1);
        $this->assertEquals(4, $topic->post_count);

        // Update record to be inactive, count should change to 3
        $post = Post::find(3);
        $post->addBehavior($this->object->track('Topic', 'post_count', function(Query $query) {
            $query->where('active', 1);
        }));
        $post->active = 0;
        $post->save(['validate' => false]);

        $topic = Topic::find(1);
        $this->assertEquals(3, $topic->post_count);

        // Update records to be active, count should change to 5
        $post = new Post();
        $post->addBehavior($this->object->track('Topic', 'post_count', function(Query $query) {
            $query->where('active', 1);
        }));
        $post->query(Query::UPDATE)->where('id', [3, 4])->save(['active' => 1]);

        $topic = Topic::find(1);
        $this->assertEquals(5, $topic->post_count);

        // Update all to be inactive
        $post->query(Query::UPDATE)->where('topic_id', 1)->save(['active' => 0]);

        $topic = Topic::find(1);
        $this->assertEquals(0, $topic->post_count);
    }

    public function testOnDeleteManyToOne() {
        $this->loadFixtures(['Topics', 'Posts']);

        $topic = Topic::find(1);
        $this->assertEquals(4, $topic->post_count);

        // Delete record with active = 0, count shouldn't change
        $post = Post::find(4);
        $post->addBehavior($this->object->track('Topic', 'post_count', function(Query $query) {
            $query->where('active', 1);
        }));
        $post->delete();

        $topic = Topic::find(1);
        $this->assertEquals(4, $topic->post_count);

        // Delete active record, could should change
        $post = Post::find(3);
        $post->addBehavior($this->object->track('Topic', 'post_count', function(Query $query) {
            $query->where('active', 1);
        }));
        $post->delete();

        $topic = Topic::find(1);
        $this->assertEquals(3, $topic->post_count);

        // Delete all records
        $post = new Post();
        $post->addBehavior($this->object->track('Topic', 'post_count', function(Query $query) {
            $query->where('active', 1);
        }));
        $post->query(Query::DELETE)->where('topic_id', 1)->save();

        $topic = Topic::find(1);
        $this->assertEquals(0, $topic->post_count);
    }

}