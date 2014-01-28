<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Titon\Db\Behavior\TimestampBehavior;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Country;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;
use \Exception;

/**
 * Test class for Titon\Model\Model.
 */
class ModelTest extends TestCase {

    /**
     * Test behaviors are passed to the table layer.
     */
    public function testAddBehavior() {
        $user = new User();
        $user->addBehavior(new TimestampBehavior());

        $this->assertTrue($user->getRepository()->hasBehavior('Timestamp'));
    }

    /**
     * Test that relations are set from the property definitions.
     */
    public function testRelations() {
        $user = new User();

        $this->assertTrue($user->getRepository()->hasRelation('Profile')); // has one
        $this->assertTrue($user->getRepository()->hasRelation('Country')); // belongs to

        $profile = new Profile();

        $this->assertTrue($profile->getRepository()->hasRelation('User')); // belongs to

        $country = new Country();

        $this->assertTrue($country->getRepository()->hasRelation('Users')); // has many

        $book = new Book();

        $this->assertTrue($book->getRepository()->hasRelation('Genres')); // belongs to many
    }

    /**
     * Test that a model instance is returned from a find call.
     */
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

    /**
     * Test that direct record deletion works correctly.
     */
    public function testDelete() {
        $this->loadFixtures('Users', 'Profiles');

        $user = User::find(1);

        $this->assertEquals(1, $user->id);
        $this->assertTrue($user->exists());
        $this->assertTrue($user->getRepository()->exists(1)); // check DB directly

        $user->delete();

        $this->assertEquals(null, $user->id);
        $this->assertFalse($user->exists());
        $this->assertFalse($user->getRepository()->exists(1)); // check DB directly

        try {
            $user->delete(); // should throw exception second time
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Records with no ID throw an error
        $user2 = User::find(10);

        try {
            $user2->delete();
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Records with ID and no record should return false
        $user3 = new User();
        $user3->id = 15;

        $this->assertEquals(0, $user3->delete());
    }

    /**
     * Test that updating a record via active record pattern works.
     */
    public function testUpdateViaSave() {
        $this->loadFixtures('Users');

        $user = User::find(1);
        $time = date('Y-m-d H:i:s');

        $this->assertArraysEqual([
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
        ], $user->toArray(), true);

        $user->username = 'mj';
        $user->modified = $time;

        $this->assertEquals(1, $user->save(['validate' => false])); // record of ID on success

        $this->assertArraysEqual([
            'id' => 1,
            'country_id' => 1,
            'username' => 'mj',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => $time
        ], $user->toArray(), true);

        // Without a find() first
        $user = new User();
        $user->id = 1;
        $user->username = 'gearvOsh';

        $this->assertEquals(1, $user->save()); // record of ID on success

        $this->assertArraysEqual([
            'id' => 1,
            'country_id' => 1,
            'username' => 'gearvOsh',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'age' => 25,
            'created' => '1988-02-26 21:22:34',
            'modified' => $time
        ], User::find(1)->toArray(), true);
    }

    /**
     * Test that inserting a record via active record pattern works.
     */
    public function testInsertViaSave() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->username = 'ironman';
        $user->firstName = 'Tony';
        $user->lastName = 'Stark';

        $this->assertFalse($user->exists());
        $this->assertEquals(6, $user->save(['validate' => false]));
        $this->assertTrue($user->exists());

        $this->assertArraysEqual([
            'id' => 6,
            'country_id' => null,
            'username' => 'ironman',
            'firstName' => 'Tony',
            'lastName' => 'Stark',
            'password' => null,
            'email' => null,
            'age' => null,
            'created' => null,
            'modified' => null
        ], User::find(6)->toArray(), true);
    }

    /**
     * Test that fill pays attention to fillable and guarded.
     */
    public function testFill() {
        $user = new User();
        $user->fill(['country_id' => 1, 'username' => 'miles', 'firstName' => 'Miles', 'lastName' => 'Johnson', 'password' => '1Z5895jf72yL77h', 'email' => 'miles@email.com', 'age' => 25, 'created' => '1988-02-26 21:22:34']);

        $this->assertEquals([
            'username' => 'miles',
            'firstName' => 'Miles'
        ], $user->toArray());

        $user->fill(['username' => 'batman']);

        $this->assertEquals(['username' => 'batman'], $user->toArray());

        $profile = new Profile();
        $profile->fill(['user_id' => 4, 'lastLogin' => '2012-02-03 21:22:34', 'currentLogin' => '2013-06-06 19:11:03']);

        $this->assertEquals([], $profile->toArray()); // fully guarded
    }

    /**
     * Test if a column is fillable.
     */
    public function testIsFillable() {
        $user = new User();
        $profile = new Profile();

        $this->assertTrue($user->isFillable('username'));
        $this->assertFalse($user->isFillable('password'));
        $this->assertTrue($profile->isFillable('lastLogin')); // All allowed
    }

    /**
     * Test if a column is guarded.
     */
    public function testIsGuarded() {
        $user = new User();
        $profile = new Profile();

        $this->assertFalse($user->isGuarded('username'));
        $this->assertTrue($user->isGuarded('password'));
        $this->assertTrue($profile->isGuarded('lastLogin')); // All denied
    }

    /**
     * Test if all columns are guarded.
     */
    public function testIsFullyGuarded() {
        $user = new User();
        $profile = new Profile();

        $this->assertFalse($user->isFullyGuarded());
        $this->assertTrue($profile->isFullyGuarded());
    }

    /**
     * Test that validation triggers.
     */
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