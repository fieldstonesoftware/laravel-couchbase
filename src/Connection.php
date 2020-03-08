<?php

namespace Fieldstone\Couchbase;

use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\ClusterOptions;
use Couchbase\QueryOptions;
use Couchbase\QueryScanConsistency;
use Fieldstone\Couchbase\Events\QueryFired;
use Fieldstone\Couchbase\Query\Builder as QueryBuilder;

class Connection extends \Illuminate\Database\Connection
{
    const AUTH_TYPE_USER_PASSWORD = 'password';
    const AUTH_TYPE_CLUSTER_ADMIN = 'cluster';
    const AUTH_TYPE_NONE = 'none';

    /**
     * The Couchbase Cluster
     * @var Cluster
     */
    protected $connection;

    /**
     * The Couchbase Bucket
     * @var Bucket
     */
    protected $bucket;

    /**
     * The Couchbase Collection
     * @var Bucket
     */
    protected $collection;

    /** @var string[] */
    protected $metrics;

    /** @var int  default consistency */
//    protected $consistency = N1qlQuery::REQUEST_PLUS;

    /**
     * Create a new Couchbase DB connection instance.
     *
     * @param  array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        // Get the Cluster Address from Config
        $address = $this->getAddress($config);

        // Set connection (Cluster)
        $this->connection = $this->createConnection($address, $config);

        // Set Bucket - Opens the Connection
        $this->bucket = $this->connection->bucket($this->getDefaultBucketName());

        // Set Collection
        $this->collection = $this->bucket->defaultCollection();

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
        // intentionally not calling the base ctor
    }

    /**
     * Get the default post processor instance.
     *
     * @return Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor;
    }

    /**
     * Get the used bucket name.
     *
     * @return string
     */
    public function getDefaultBucketName()
    {
        return $this->config['bucket'];
    }

    /**
     * Begin a fluent query against a set of document types.
     *
     * @param string $documentType
     * @return Query\Builder
     * @throws \Exception
     */
    public function builder($documentType)
    {
        $query = new QueryBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
        return $query->from($documentType);
    }

    /**
     * @return QueryBuilder
     * @throws \Exception
     */
    public function query()
    {
        return $this->builder(null);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string $query
     * @param  array $bindings
     * @return bool
     * @throws \Exception
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $result = $this->runN1qlQuery($query, $bindings);

            return $result->status === 'success';
        });
    }

    /**
     *
     * @param $query
     * @return mixed
     */
    protected function executeQuery($query)
    {
        return $this->bucket->query($query, true);
    }

    /**
     * {@inheritdoc}
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->selectWithMeta($query, $bindings, $useReadPdo)->rows;
    }

    /**
     * {@inheritdoc}
     */
    public function selectWithMeta($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $result = $this->runN1qlQuery($query, $bindings);
            if (isset($result->rows)) {
                $result->rows = json_decode(json_encode($result->rows), true);
            }
            return $result;
        });
    }

    /**
     * @param string $query
     * @param array $bindings
     *
     * @return int|mixed
     * @throws \Exception
     */
    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array $bindings
     *
     * @return int|\stdClass
     * @throws \Exception
     */
    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param string $query
     * @param array $bindings
     *
     * @return int|\stdClass
     * @throws \Exception
     */
    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * @param       $query
     * @param array $bindings
     *
     * @return mixed
     * @throws \Exception
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }
            $result = $this->runN1qlQuery($query, $bindings);
            $this->metrics = (isset($result->metrics)) ? $result->metrics : [];

            return (isset($result->rows[0])) ? $result->rows[0] : false;
        });
    }

    /**
     * @param string $n1ql
     * @param array $bindings Positional Parameters
     * @param int $scanConsistency
     * @return mixed
     */
    protected function runN1qlQuery($n1ql
        , array $bindings
        , $scanConsistency = QueryScanConsistency::NOT_BOUNDED
    ) {
        $options = new QueryOptions();
        $options->positionalParameters($bindings);
        $options->scanConsistency($scanConsistency);

        $fSuccess = false;
        try {
            $result = $this->getCouchbaseBucket()->query($n1ql, $options);
//            $result = $this->connection->query($n1ql, $options);
            $fSuccess = true;
        } finally {
            $this->logQueryFired($n1ql, [
                'consistency' => $scanConsistency,
                'positionalParams' => $bindings,
                'success' => $fSuccess
            ]);
        }
        return $result;
    }

    /**
     * @param string $query
     * @param array $options
     */
    public function logQueryFired(string $query, array $options)
    {
        $this->event(new QueryFired($query, $options));
    }

    /**
     * Begin a fluent query against documents with given type.
     *
     * @param string $documentType
     * @return Query\Builder
     * @throws \Exception
     */
    public function type($documentType)
    {
        return $this->builder($documentType);
    }

    /**
     * Begin a fluent query against documents with given type.
     *
     * @param string $documentType
     * @return Query\Builder
     * @throws \Exception
     */
    public function table($documentType)
    {
        return $this->builder($documentType);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return Schema\Builder
     */
    public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }

    /**
     * Get the Couchbase bucket object.
     *
     * @return Bucket
     */
    public function getCouchbaseBucket() : Bucket
    {
        return $this->bucket;
    }

    /**
     * Get the query grammar used by the connection.
     */
    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    /**
     * return Cluster object.
     *
     * @return Cluster
     */
    public function getCouchbaseCluster()
    {
        return $this->connection;
    }

    /**
     * Create a new Couchbase connection.
     *
     * @param $address
     * @param array $config
     * @return Cluster
     */
    protected function createConnection($address, array $config)
    {
        $options = new ClusterOptions();
        if (!empty($config['username']) && !empty($config['password'])) {
            $options->credentials(strval($config['username']), strval($config['password']));
        }

        return new Cluster($address, $options);
    }

    /**
     * Disconnect from the underlying Couchbase connection.
     */
    public function disconnect()
    {
        unset($this->connection);
    }

    /**
     * Create an address string from a configuration.
     *
     * @param  array $config
     * @return string
     */
    protected function getAddress(array $config)
    {
        // Treat host option as array of hosts
        $hosts = is_array($config['host']) ? $config['host'] : [$config['host']];

        foreach ($hosts as &$host) {
            // Check if we need to add a port to the host
            if (strpos($host, ':') === false && !empty($config['port'])) {
                $host = $host . ':' . $config['port'];
            }
        }

        return 'couchbase://' . implode(',', $hosts);
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param  int $start
     * @return float
     */
    public function getElapsedTime($start)
    {
        return parent::getElapsedTime($start);
    }

    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function getDriverName()
    {
        return 'couchbase';
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return Schema\Grammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return new Schema\Grammar;
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return Query\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar();
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->bucket, $method], $parameters);
    }
}
