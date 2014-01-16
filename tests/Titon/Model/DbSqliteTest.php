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

/**
 * Test class for SQLite.
 */
class DbSqliteTest extends DbMysqlTest {

    /**
     * Setup the DB once, not before every test.
     */
    public function setUpDb() {
        Registry::factory('Titon\Db\Connection')
            ->addDriver(new SqliteDriver('default', [
                'database' => 'titon_test',
                'host' => '127.0.0.1',
                'user' => 'root',
                'pass' => ''
            ]));
    }

    /**
     * Test record creation with insertMany().
     */
    public function testCreateMultiple() {
        $this->markTestSkipped('SQLite does not support compound multi-insert');
    }

}