<?php
namespace Titon\Model;

use Titon\Db\Query;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Topic;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

class AbstractDbTest extends TestCase {

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

    public function testDecrement() {
        $this->loadFixtures('Topics');

        $this->assertEquals(new Topic(['post_count' => 4]), Topic::select('post_count')->where('id', 1)->first());

        Topic::decrement(1, ['post_count' => 1]);

        $this->assertEquals(new Topic(['post_count' => 3]), Topic::select('post_count')->where('id', 1)->first());
    }

    public function testDelete() {
        $this->loadFixtures('Users');

        $user = User::find(1);

        $this->assertTrue($user->exists());
        $this->assertSame(1, $user->delete());
        $this->assertFalse($user->exists());
    }

    public function testDeleteBy() {
        $this->loadFixtures('Users');

        $this->assertTrue(User::find(1)->exists());

        $this->assertSame(1, User::deleteBy(1));

        $this->assertFalse(User::find(1)->exists());
    }

    public function testDeleteMany() {
        $this->loadFixtures('Users');

        $this->assertEquals(3, User::deleteMany(function(Query $query) {
            $query->where('age', '>', 30);
        }));
    }

    public function testFind() {
        $this->loadFixtures('Users');

        $user1 = User::find(1);
        $user2 = User::find(10);

        $this->assertInstanceOf('Titon\Model\Model', $user1);
        $this->assertInstanceOf('Titon\Model\Model', $user2);

        $this->assertTrue($user1->exists());
        $this->assertFalse($user2->exists());

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
        ], $user1->toArray());

        $this->assertEquals([], $user2->toArray());
    }

    public function testIncrement() {
        $this->loadFixtures('Topics');

        $this->assertEquals(new Topic(['post_count' => 4]), Topic::select('post_count')->where('id', 1)->first());

        Topic::increment(1, ['post_count' => 3]);

        $this->assertEquals(new Topic(['post_count' => 7]), Topic::select('post_count')->where('id', 1)->first());
    }

    public function testInsert() {
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

        $this->assertEquals(6, $last_id);

        $this->assertEquals([
            'id' => 6,
            'country_id' => 1,
            'username' => 'ironman',
            'password' => '7NAks9193KAkjs1',
            'email' => 'ironman@email.com',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'age' => 38,
            'created' => null,
            'modified' => null
        ], User::find($last_id)->toArray());
    }

    public function testInsertMany() {
        $this->loadFixtures('Users');

        User::truncate(); // Empty first

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

    public function testUpdateBy() {
        $this->loadFixtures('Users');

        $this->assertEquals(1, User::updateBy(1, [
            'country_id' => 3,
            'username' => 'milesj'
        ]));

        $this->assertEquals([
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
        ], User::find(1)->toArray());
    }

    public function testUpdateMany() {
        $this->loadFixtures('Users');

        $this->assertEquals(3, User::updateMany(['country_id' => null], function(Query $query) {
            $query->where('age', '>', 30);
        }));
    }

}