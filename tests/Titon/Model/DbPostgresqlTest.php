<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model;

use Titon\Common\Registry;
use Titon\Db\Pgsql\PgsqlDriver;

/**
 * Test class for PostgreSQL.
 */
class DbPostgresqlTest extends DbMysqlTest {

    /**
     * Setup the DB once, not before every test.
     */
    public function setUpDb() {
        Registry::factory('Titon\Db\Connection')
            ->addDriver(new PgsqlDriver('default', [
                'database' => 'titon_test',
                'host' => '127.0.0.1',
                'user' => 'postgres',
                'pass' => getenv('TRAVIS') ? '' : 'test123'
            ]));
    }

}