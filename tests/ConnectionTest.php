<?php
namespace Fieldstone\Couchbase\Test;

use Couchbase\Bucket;
use Couchbase\Cluster;
use Illuminate\Support\Facades\DB;

class ConnectionTest extends TestCase
{
    public function testConnection()
    {
        $connection = DB::connection('couchbase-default');
        $this->assertInstanceOf('Fieldstone\Couchbase\Connection', $connection);
    }

    public function testConnectionSingleton()
    {
        /** @var \Fieldstone\Couchbase\Connection $c1 */
        /** @var \Fieldstone\Couchbase\Connection $c2 */
        $c1 = DB::connection();
        $c2 = DB::connection('couchbase-default');
        $this->assertEquals(spl_object_hash($c1), spl_object_hash($c2));
        $this->assertEquals(spl_object_hash($c1->getCouchbaseCluster()), spl_object_hash($c2->getCouchbaseCluster()));

        $c1 = DB::connection();
        $c2 = DB::connection('couchbase-not-default');
        $this->assertNotEquals(spl_object_hash($c1), spl_object_hash($c2));
        $this->assertNotEquals(spl_object_hash($c1->getCouchbaseCluster()), spl_object_hash($c2->getCouchbaseCluster()));

        $c1 = DB::connection('couchbase-not-default');
        $c2 = DB::connection('couchbase-not-default');
        $this->assertEquals(spl_object_hash($c1), spl_object_hash($c2));
        $this->assertEquals(spl_object_hash($c1->getCouchbaseCluster()), spl_object_hash($c2->getCouchbaseCluster()));
    }

    public function testDb()
    {
        $connection = DB::connection();
        $this->assertInstanceOf(Bucket::class, $connection->getCouchbaseBucket());
        $this->assertInstanceOf(Cluster::class, $connection->getCouchbaseCluster());

        $connection = DB::connection('couchbase-default');
        $this->assertInstanceOf(Bucket::class, $connection->getCouchbaseBucket());
        $this->assertInstanceOf(Cluster::class, $connection->getCouchbaseCluster());

        $connection = DB::connection('couchbase-not-default');
        $this->assertInstanceOf(Bucket::class, $connection->getCouchbaseBucket());
        $this->assertInstanceOf(Cluster::class, $connection->getCouchbaseCluster());
    }

    public function testBucketWithTypes()
    {
        $connection = DB::connection();
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->builder('unittests'));
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->table('unittests'));
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->type('unittests'));

        $connection = DB::connection('couchbase-default');
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->builder('unittests'));
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->table('unittests'));
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->type('unittests'));

        $connection = DB::connection('couchbase-not-default');
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->builder('unittests'));
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->table('unittests'));
        $this->assertInstanceOf('Fieldstone\Couchbase\Query\Builder', $connection->type('unittests'));
    }

    public function testQueryLog()
    {
        DB::connection()->enableQueryLog();

        $this->assertEquals(0, count(DB::getQueryLog()));

        DB::type('items')->get();
        $this->assertEquals(1, count(DB::getQueryLog()));

        DB::type('items')->count();
        $this->assertEquals(2, count(DB::getQueryLog()));

        DB::type('items')->where('name', 'test')->update(['name' => 'test']);
        $this->assertEquals(3, count(DB::getQueryLog()));

        DB::type('items')->where('name', 'test')->delete();
        $this->assertEquals(4, count(DB::getQueryLog()));

        DB::type('items')->insert(['name' => 'test']);
        $this->assertEquals(4, count(DB::getQueryLog())); // insert does not use N1QL-queries

    }

    public function testDriverName()
    {
        $driver = DB::connection()->getDriverName();
        $this->assertEquals('couchbase', $driver);

        $driver = DB::connection('couchbase-default')->getDriverName();
        $this->assertEquals('couchbase', $driver);

        $driver = DB::connection('couchbase-not-default')->getDriverName();
        $this->assertEquals('couchbase', $driver);
    }
}
