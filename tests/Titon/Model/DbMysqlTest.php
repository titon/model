<?php
namespace Titon\Model;

use Titon\Common\Config;
use Titon\Db\Database;
use Titon\Db\Mysql\MysqlDriver;

class DbMysqlTest extends AbstractDbTest {

    protected function setUp() {
        parent::setUp();

        Database::registry()->addDriver('default', new MysqlDriver(Config::get('db')));
    }

}