<?php
namespace Titon\Model;

use Titon\Common\Config;
use Titon\Db\Database;
use Titon\Db\Pgsql\PgsqlDriver;

class DbPostgresqlTestDISABLED extends DbMysqlTest {

    protected function setUp() {
        parent::setUp();

        $db = Config::get('db');
        $db['user'] = 'postgres';
        $db['pass'] = 'test123';

        Database::registry()->addDriver('default', new PgsqlDriver($db));
    }

}