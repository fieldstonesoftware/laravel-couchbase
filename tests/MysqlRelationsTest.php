<?php
namespace Fieldstone\Couchbase\Test;

use Fieldstone\Couchbase\Test\Model\Book;
use Fieldstone\Couchbase\Test\Model\MysqlBook;
use Fieldstone\Couchbase\Test\Model\MysqlRole;
use Fieldstone\Couchbase\Test\Model\MysqlUser;
use Fieldstone\Couchbase\Test\Model\Role;
use Fieldstone\Couchbase\Test\Model\User;
use Illuminate\Database\MySqlConnection;

class MysqlRelationsTest extends TestCase
{
    public function setUp() : void
    {
        parent::setUp();

        MysqlUser::executeSchema();
        MysqlBook::executeSchema();
        MysqlRole::executeSchema();

        // in case we failed and this is a re-run
        $this->truncateModels();
    }

    public function tearDown() : void
    {
        $this->truncateModels();
    }

    public function truncateModels()
    {
        MysqlUser::truncate();
        MysqlRole::truncate();
        MysqlBook::truncate();
        User::truncate();
        Role::truncate();
        Book::truncate();
    }

    public function testMysqlRelations()
    {
        $user = new MysqlUser;
        $this->assertInstanceOf(MysqlUser::class, $user);
        $this->assertInstanceOf(MySqlConnection::class, $user->getConnection());

        // Mysql User
        $user->name = "John Doe";
        $user->save();
        $this->assertTrue(is_int($user->id));

        // SQL has many
        $book = new Book(['title' => 'Game of Thrones']);
        $user->books()->save($book);
        $user = $user->fresh(); // refetch
        $this->assertEquals(1, count($user->books));

        // Couchbase belongs to
        $book = $user->books()->first(); // refetch
        $this->assertEquals('John Doe', $book->mysqlAuthor->name);

        // SQL has one
        $role = new Role(['type' => 'admin']);
        $user->role()->save($role);
        $user = $user->fresh(); // get fresh from DB
        $this->assertEquals('admin', $user->role->type);

        // Couchbase belongs to
        $role = $user->role()->first(); // refetch
        $this->assertEquals('John Doe', $role->mysqlUser->name);

        // Couchbase User
        $user = new User;
        $user->name = "John Doe";
        $user->save();

        // Couchbase has many
        $book = new MysqlBook(['title' => 'Game of Thrones']);
        $user->mysqlBooks()->save($book);
        $user = User::find($user->_id); // refetch
        $this->assertEquals(1, count($user->mysqlBooks));

        // SQL belongs to
        $book = $user->mysqlBooks()->first(); // refetch
        $this->assertEquals('John Doe', $book->author->name);

        // Couchbase has one
        $role = new MysqlRole(['type' => 'admin']);
        $user->mysqlRole()->save($role);
        $user = User::find($user->_id); // refetch
        $this->assertEquals('admin', $user->mysqlRole->type);

        // SQL belongs to
        $role = $user->mysqlRole()->first(); // refetch
        $this->assertEquals('John Doe', $role->user->name);
    }
}
