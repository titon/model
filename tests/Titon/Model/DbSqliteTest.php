<?php
namespace Titon\Model;

use Titon\Common\Config;
use Titon\Db\Database;
use Titon\Db\Query;
use Titon\Db\Sqlite\SqliteDriver;

class DbSqliteTest extends DbMysqlTest {

    protected function setUp() {
        parent::setUp();

        Database::registry()->addDriver('default', new SqliteDriver(Config::get('db')));
    }

    public function testInsertMany() {
        $this->markTestSkipped('SQLite does not support multi-insert');
    }

}