<?php
namespace Titon\Model;

use Titon\Common\Config;
use Titon\Db\Database;
use Titon\Db\Entity;
use Titon\Db\Mysql\MysqlDriver;
use Titon\Db\Query;
use Titon\Db\Repository;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Country;
use Titon\Test\Stub\Model\Genre;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\User;

class DbMysqlTest extends AbstractDbTest {

    protected function setUp() {
        parent::setUp();

        Database::registry()->addDriver('default', new MysqlDriver(Config::get('db')));
    }

}