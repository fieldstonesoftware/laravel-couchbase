<?php
namespace Fieldstone\Couchbase\Test;

use CouchbaseBucket;
use CouchbaseCluster;
use CouchbaseException;
use CouchbaseN1qlQuery;
use Illuminate\Support\Facades\DB;
use ReflectionExtension;

class ConnectionTest extends TestCase
{
    public function testConnection()
    {
        $connection = DB::connection('couchbase-default');
        $this->assertInstanceOf('Fieldstone\Couchbase\Connection', $connection);
    }

    public function testPrintVersions()
    {
        $ext = new ReflectionExtension('couchbase');
        echo "\n\n---- PHP Info ----";
        echo "\n".'PHP:'.phpversion();
        echo "\n\n---- Couchbase Client Info ----";
        echo "\n".$ext->info();

        $connection = DB::connection();
        $diag = $connection->getBucket()->diag();
        echo "---- Couchbase Connection Diagnostics ----";
        echo "\n SDK: ".$diag['sdk'];
        echo "\n Version: ".$diag['version'];
        echo "\n\n";

        // phpunit fodder
        $this->assertEquals(1, 1);
    }

    public function testBasicOperations()
    {
        $bucketName = "test-ing";

        // Establish username and password for bucket-access
        $authenticator = new \Couchbase\PasswordAuthenticator();
        $authenticator->username('admin')->password('password');

        // Connect to Couchbase Server - using address of a KV (data) node
        $cluster = new CouchbaseCluster("couchbase://127.0.0.1");

        // Authenticate, then open bucket
        $cluster->authenticate($authenticator);
        $bucket = $cluster->openBucket($bucketName);

        // Store a document
        echo "Storing u:king_arthur\n";
        $result = $bucket->upsert('u:king_arthur', array(
            "email" => "kingarthur@couchbase.com",
            "interests" => array("African Swallows")
        ));

        var_dump($result);

        // Retrieve a document
        echo "Getting back u:king_arthur\n";
        $result = $bucket->get("u:king_arthur");
        var_dump($result->value);

        // Replace a document
        echo "Replacing u:king_arthur\n";
        $doc = $result->value;
        array_push($doc->interests, 'PHP 7');
        $bucket->replace("u:king_arthur", $doc);
        var_dump($result);

        echo "Creating primary index\n";
        // Before issuing a N1QL Query, ensure that there is
        // is actually a primary index.
        try {
            // Do not override default name; fail if it already exists, and wait for completion
            $bucket->manager()->createN1qlPrimaryIndex('', false, false);
            echo "Primary index has been created\n";
        } catch (CouchbaseException $e) {
            printf("Couldn't create index. Maybe it already exists? (code: %d)\n", $e->getCode());
        }

        // Query with parameters
        $query = CouchbaseN1qlQuery::fromString("SELECT * FROM `$bucketName` WHERE \$p IN interests");
        $query->namedParams(array("p" => "African Swallows"));
        echo "Parameterized query:\n";
        var_dump($query);
        $rows = $bucket->query($query);
        echo "Results:\n";
        var_dump($rows);

        // phpunit fodder
        $this->assertEquals(1, 1);
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
        $this->assertInstanceOf(CouchbaseBucket::class, $connection->getCouchbaseBucket());
        $this->assertInstanceOf(CouchbaseCluster::class, $connection->getCouchbaseCluster());

        $connection = DB::connection('couchbase-default');
        $this->assertInstanceOf(CouchbaseBucket::class, $connection->getCouchbaseBucket());
        $this->assertInstanceOf(CouchbaseCluster::class, $connection->getCouchbaseCluster());

        $connection = DB::connection('couchbase-not-default');
        $this->assertInstanceOf(CouchbaseBucket::class, $connection->getCouchbaseBucket());
        $this->assertInstanceOf(CouchbaseCluster::class, $connection->getCouchbaseCluster());
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
