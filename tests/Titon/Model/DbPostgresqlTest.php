<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Titon\Db\Database;
use Titon\Db\Pgsql\PgsqlDriver;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\User;

/**
 * Test class for PostgreSQL.
 */
class DbPostgresqlTest extends DbMysqlTest {

    /**
     * Setup the DB once, not before every test.
     */
    public static function setUpBeforeClass() {
        Database::registry()
            ->addDriver('default', new PgsqlDriver([
                'database' => 'titon_test',
                'host' => '127.0.0.1',
                'user' => 'postgres',
                'pass' => getenv('TRAVIS') ? '' : 'test123'
            ]));

        // Remove singletons
        User::flushInstances();
        Book::flushInstances();
        Series::flushInstances();
        Profile::flushInstances();
    }

}