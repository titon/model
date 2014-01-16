<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Titon\Common\Registry;
use Titon\Db\Query;
use Titon\Db\Sqlite\SqliteDriver;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\User;

/**
 * Test class for SQLite.
 */
class DbSqliteTest extends DbMysqlTest {

    /**
     * Setup the DB once, not before every test.
     */
    public static function setUpBeforeClass() {
        Registry::factory('Titon\Db\Connection')
            ->addDriver(new SqliteDriver('default', [
                'database' => 'titon_test',
                'host' => '127.0.0.1',
                'user' => 'root',
                'pass' => ''
            ]));

        // Remove singletons
        User::flushInstances();
        Book::flushInstances();
        Series::flushInstances();
        Profile::flushInstances();
    }

    /**
     * Test record creation with insertMany().
     */
    public function testCreateMultiple() {
        $this->markTestSkipped('SQLite does not support compound multi-insert');
    }

}