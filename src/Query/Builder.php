<?php

namespace Fieldstone\Couchbase\Query;

use Couchbase\Document;
use Couchbase\Exception;
use Fieldstone\Couchbase\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as IlluminateQueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Fieldstone\Couchbase\Query\Grammar as QueryGrammar;
use Fieldstone\Couchbase\Connection;

class Builder extends IlluminateQueryBuilder
{

    /**
     * The column projections.
     *
     * @var array
     */
    public $projections;

    public $forIns = [];

    /**
     * The cursor timeout value.
     * @var int
     */
    public $timeout;

    /**
     * The cursor hint value.
     * @var int
     */
    public $hint;

    /**
     * Indicate if we are executing a pagination query.
     * @var bool
     */
    public $paginating = false;

    /**
     * @var array
     */
    public $options;

    /**
     * All of the available clause operators.
     * @var array
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like', 'between', 'ilike', '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp', 'exists', 'mod', 'where', 'all', 'size', 'regex', 'text', 'slice',
        'elemmatch', 'geowithin', 'geointersects', 'near', 'nearsphere', 'geometry', 'maxdistance', 'center',
        'centersphere', 'box', 'polygon', 'uniquedocs'
    ];

    /**
     * Operator conversion.
     * @var array
     */
    protected $conversion = [
        '=' => '=', '!=' => '$ne', '<>' => '$ne', '<' => '$lt', '<=' => '$lte', '>' => '$gt', '>=' => '$gte'
    ];

    /**
     * Keys used via 'USE KEYS'
     * @var null|array|string
     */
    public $keys = null;

    /**
     * Var used because it is called by magic for compileUse() / has to be not null
     * @var true
     */
    public $use = true;

    /**
     * Indexes used via 'USE INDEX'
     * @var array
     */
    public $indexes = [];

    /** @var string[]  returning-clause */
    public $returning = ['*'];

    /**
     * The field in the document that indicates its type
     * This typically maps to a model
     * @var string
     */
    public $sDocTypeKey;
    public $sType;  // type of document

    /**
     * The field in the document that indicates which tenant
     * the document belongs to
     * @var string
     */
    public $sTenantIdKey;
    public $sTenantId = null;  // the tenant ID - default to null - unused

    /**
     * Couchbase does not store the key in the document by default
     * but when we embed models in models, its helpful to have the
     * ID in all documents. This is what you want to call the field.
     * @var string
     */
    public $sInDocIdKey;

    /**
     * Create a new query builder instance.
     *
     * @param ConnectionInterface $connection
     * @param QueryGrammar $grammar
     * @param Processor $processor
     * @throws \Exception
     */
    public function __construct(
        ConnectionInterface $connection,
        QueryGrammar $grammar = null,
        Processor $processor = null
    ) {
        if(!($connection instanceof Connection)) {
            throw new \Exception('Argument 1 passed to '.get_class($this).'::__construct() must be an instance of '
                .Connection::class.', instance of '.get_class($connection).' given.');
        }
        if(!($grammar === null || $grammar instanceof QueryGrammar)) {
            throw new \Exception('Argument 2 passed to '.get_class($this).'::__construct() must be an instance of '
                .QueryGrammar::class.', instance of '.get_class($grammar).' given.');
        }

        parent::__construct($connection, $grammar, $processor);

        $this->sDocTypeKey = config('couchbase.type_key','doc_type');
        $this->sTenantIdKey = config('couchbase.tenant_id_key','tenant_id');
        $this->sInDocIdKey = config('couchbase.in_doc_id_key','key_id');

        // add the type key to the operator list
        array_push($this->operators, $this->sDocTypeKey);

        $this->returning([$this->connection->getDefaultBucketName() . '.*']);
    }

    /**
     * @param array|string $keys
     * @return $this
     * @throws Exception
     */
    public function useKeys($keys)
    {
        if (!empty($this->indexes)) {
            throw new Exception('Only one of useKeys or useIndex can be used, not both.');
        }
        if (is_null($keys)) {
            $keys = [];
        }
        $this->keys = $keys;

        return $this;
    }

    /**
     * @param string $name
     * @param string $type
     * @return $this
     * @throws Exception
     */
    public function useIndex($name, $type = QueryGrammar::INDEX_TYPE_GSI)
    {
        if ($this->keys !== null) {
            throw new Exception('Only one of useKeys or useIndex can be used, not both.');
        }
        $this->indexes[] = [
            'name' => $name,
            'type' => $type
        ];

        return $this;
    }

    /**
     * @param array $column
     * @return $this
     */
    public function returning(array $column = ['*'])
    {
        $this->returning = $column;

        return $this;
    }

    /**
     * Set the type of document we're looking for. This is the equivalent to a table
     * name in an RDBMS. In Couchbase, we don't have tables - we have documents
     * with types. The default Bucket is used to pull from.
     *
     * @param  string $type
     * @return $this
     */
    public function from($type, $as = NULL)
    {
        // the "table" name (from) is actually the bucket name for Couchbase
        $this->from = $this->connection->getDefaultBucketName();

        // Set the document type
        $this->sType = $type;

        // Additionally, we add a where clause for the document type using
        // the configuration specified document type key (the field name in the doc)
        if(!is_null($type)){
            $this->where($this->sDocTypeKey, $type);
        }

        return $this;
    }

    /**
     * Create a new query instance for nested where condition.
     * @return \Illuminate\Database\Query\Builder
     * @throws \Exception
     */
    public function forNestedWhere()
    {
        // ->from($this->from) is wrong, and ->from($this->type) is redundant in nested where
        return $this->newQuery()->from(null);
    }

    /**
     * Set the projections.
     * @param  array $columns
     * @return $this
     */
    public function project($columns)
    {
        $this->projections = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     * @return Collection
     */
    public function get($columns = ['*'])
    {
        return $this->getWithMeta($columns)->rows;
    }

    /**
     * Execute the query as a "select" statement.
     * Include the query meta data in the return with the results
     * @param array $columns
     * @return \stdClass
     */
    public function getWithMeta($columns = ['*'])
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        /** @var Processor $processor */
        $processor = $this->processor;
        $results = $processor->processSelectWithMeta($this, $this->runSelectWithMeta());

        $this->columns = $original;
        $results->rows = isset($results->rows) ? collect($results->rows) : collect();

        return $results;
    }

    /**
     * Set the cursor timeout in seconds.
     * @param  int $seconds
     * @return $this
     */
    public function timeout($seconds)
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set the cursor hint.
     * @param  mixed $index
     * @return $this
     */
    public function hint($index)
    {
        $this->hint = $index;

        return $this;
    }

    /**
     * Execute a query for a single record by ID.
     * @param mixed $id
     * @param array $columns
     * @return mixed|static
     * @throws Exception
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id) === true) {
            return $this->useKeys($id)->get($columns);
        }
        return $this->useKeys($id)->first($columns);
    }

    /**
     * Generate the unique cache key for the current query.
     * @return string
     */
    public function generateCacheKey()
    {
        $key = [
            'bucket' => $this->from,
            $this->sDocTypeKey => $this->sType,
            'tenant' => $this->sTenantId,
            'wheres' => $this->wheres,
            'columns' => $this->columns,
            'groups' => $this->groups,
            'orders' => $this->orders,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'aggregate' => $this->aggregate,
        ];

        return md5(serialize(array_values($key)));
    }

    /**
     * Execute an aggregate function on the database.
     * @param  string $function
     * @param  array $columns
     * @return mixed
     */
    public function aggregate($function, $columns = ['*'])
    {
        // added orders to ignore...
        $results = $this->cloneWithout(['orders', 'columns'])
            ->cloneWithoutBindings(['select'])
            ->setAggregate($function, $columns)
            ->get($columns);

        if (!$results->isEmpty()) {
            return array_change_key_case((array)$results[0])['aggregate'];
        }
    }

    /**
     * Determine if any rows exist for the current query.
     * @return bool
     */
    public function exists()
    {
        return !is_null($this->first([QueryGrammar::VIRTUAL_META_ID_COLUMN]));
    }

    /**
     * Add a where between statement to the query.
     * @param  string $column
     * @param  array $values
     * @param  string $boolean
     * @param  bool $not
     * @return Builder
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'values', 'not');

        $this->addBinding($values, 'where');

        return $this;
    }


    /**
     * Set the bindings on the query builder.
     * @param  array $bindings
     * @param  string $type
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setBindings(array $bindings, $type = 'where')
    {
        parent::setBindings($bindings, $type);
        return $this;
    }

    /**
     * Add a binding to the query.
     * @param  mixed $value
     * @param  string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addBinding($value, $type = 'where')
    {
        parent::addBinding($value, $type);
        return $this;
    }

    /**
     * Set the limit and offset for a given page.
     * @param  int $page
     * @param  int $perPage
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function forPage($page, $perPage = 15)
    {
        $this->paginating = true;

        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * Insert a new document into the database. Pass the contents of the document.
     * We have no way to distinguish between a single or multiple documents being
     * inserted by the values being passed so we always great values as a single
     * document content.
     *
     * @param array $document
     * @return string|null ID of the inserted document
     * @throws \Exception
     */
    public function insert(array $document)
    {
        if (empty($document)) {
            return true;
        }

        // id is stored outside the document
        $id = '';

        // properly populate the id, tenant, type
        $this->prepareDocumentForSave($id, $document);

        // insert the document
        $result = $this->connection->getCouchbaseBucket()->upsert(
            $id, QueryGrammar::removeMissingValue($document)
        );

        if(is_null($result->error)){
            return $id;
        }

        return null;
    }

    /**
     * If the document includes an id or _id field, return that in docId.
     * Otherwise, generate a new ID and return that in docId
     * Set the tenant ID and type fields in the document if not set.
     * @param $document
     * @throws \Exception
     */
    private function prepareDocumentForSave(&$docId, &$document)
    {
        // Prepare a document ID if the document does not have one
        // if no id or _id is specified, generate one
        // unset the ID value within the document - it gets stored separately in CB
        if(!isset($document['_id']) && !isset($document['id'])){
            $docId = Model::sGetNewKeyValue($this->sType, $this->sTenantId);
        }else{ // otherwise honor what is provided
            if(isset($document['_id'])){
                $docId = $document['_id'];
                unset($document['_id']);
            }elseif(isset($document['id'])){
                $docId = $document['id'];
                unset($document['id']);
            }
        }

        // set the in-document id if not set
        if(!isset($document[$this->sInDocIdKey])){
            $document[$this->sInDocIdKey] = $docId;
        }

        // set the tenant id if not set and non-empty
        if(!isset($document[$this->sTenantIdKey]) && !empty($this->sTenantId)){
            $document[$this->sTenantIdKey] = $this->sTenantId;
        }

        // set the type if not set
        if(!isset($document[$this->sDocTypeKey])){
            $document[$this->sDocTypeKey] = $this->sType;
        }
    }

    /**
     * Update a record in the database.
     * @param  array $values
     * @return int
     */
    public function update(array $values)
    {
        // replace MissingValue in 2nd or deeper levels
        foreach ($values as $key => $value) {
            $values[$key] = QueryGrammar::removeMissingValue($value);
        }
        return parent::update($values);
    }

    /**
     * Insert a new record and get the value of the primary key.
     * @param array $document
     * @param null $sequence NOT USED
     * @return int
     * @throws \Exception
     */
    public function insertGetId(array $document, $sequence = null)
    {
        return $this->insert($document);
    }

    /**
     * Get an array with the values of a given column.
     * @param  string $column
     * @param  string|null $key
     * @return Collection
     */
    public function pluck($column, $key = null)
    {
        $results = $this->get(is_null($key) ? [$column] : [$column, $key]);

        // Convert ObjectID's to strings
        if ($key == '_id') {
            $results = $results->map(function ($item) {
                $item['_id'] = (string)$item['_id'];
                return $item;
            });
        }

        return $results->pluck($column, $key);
    }

    /**
     * Run a truncate statement on the table.
     */
    public function truncate()
    {
        return $this->delete();
    }

    /**
     * Append one or more values to an array.
     * @param mixed $key
     * @param mixed $value
     * @param bool $unique
     *
     * @return array|Document
     * @throws \Exception
     */
    public function push($key, $value = null, $unique = false)
    {
        if(empty($key)) throw new \Exception("Can not push to empty key!");

        $obj = $this->connection->getCouchbaseBucket()->get($this->keys);
        if (!isset($obj->value->{$key})) {
            $obj->value->{$key} = [];
        }
        if (is_array($value) && count($value) === 1) {
            $obj->value->{$key}[] = reset($value);
        } else {
            $obj->value->{$key}[] = $value;
        }
        if ($unique) {
            $array = array_map('json_encode', $obj->value->{$key});
            $array = array_unique($array);
            $obj->value->{$key} = array_map('json_decode', $array);
        }
        return $this->connection->getCouchbaseBucket()->upsert($this->keys, $obj->value);
    }

    /**
     * Remove one or more values from an array.
     * @param mixed $key
     * @param mixed $value
     * @return array|Document|null
     * @throws Exception
     * @throws \Exception
     */
    public function pull($key, $value = null)
    {
        if(empty($key)) throw new \Exception("Can not pull from empty key!");

        try {
            $obj = $this->connection->getCouchbaseBucket()->get($this->keys);
        } catch (Exception $e) {
            if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                trigger_error('Tying to pull a value from non existing document ' . json_encode($this->keys) . '.',
                    E_USER_WARNING);
                return null;
            }
            throw $e;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        if (!isset($obj->value->{$key})) {
            trigger_error('Tying to pull a value from non existing column ' . json_encode($key) . ' in document ' . json_encode($this->keys) . '.',
                E_USER_WARNING);
            return null;
        }

        $filtered = collect($obj->value->{$key})->reject(function ($val, $key) use ($value) {
            $match = false;
            if (is_object($val)) {
                foreach ($value AS $matchKey => $matchValue) {
                    if ($val->{$matchKey} === $value[$matchKey]) {
                        $match = true;
                    }
                }
            } else {
                $match = in_array($val, $value);
            }

            return $match;
        });
        $obj->value->{$key} = $filtered->flatten()->toArray();

        return $this->connection->getCouchbaseBucket()->upsert($this->keys, $obj->value);
    }

    /**
     * Remove all of the expressions from a list of bindings.
     * @param  array $bindings
     * @return array
     */
    protected function cleanBindings(array $bindings)
    {
        return array_values(array_filter(parent::cleanBindings($bindings),
            function ($binding) {
                return !($binding instanceof MissingValue);
            }));
    }

    /**
     * Remove one or more fields.
     * @param  mixed $columns
     * @return int
     */
    public function drop($columns)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        $query = $this->getGrammar()->compileUnset($this, $columns);
        $bindings = $this->getBindings();
        return $this->connection->update($query, $bindings);
    }

    /**
     * @return QueryGrammar
     */
    public function getGrammar() : QueryGrammar
    {
        return parent::getGrammar();
    }

    /**
     * Get a new instance of the query builder.
     * @return Builder
     * @throws \Exception
     */
    public function newQuery()
    {
        return new Builder($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Run the query as a "select" statement against the connection.
     * @return \stdClass
     */
    protected function runSelectWithMeta()
    {
        $res = $this->connection->selectWithMeta(
            $this->toSql(),
            $this->getBindings(),
            !$this->useWritePdo
        );

        return $res;
    }

    /**
     * Convert a key to ObjectID if needed.
     * @param  mixed $id
     * @return mixed
     */
    public function convertKey($id)
    {
        return $id;
    }

    /**
     * Add a FOR ... IN query
     * @param  string $column
     * @param  mixed $value
     * @param  string $alias
     * @param  array $values
     * @return \Illuminate\Database\Query\Builder|static
     * @throws \InvalidArgumentException
     */
    public function forIn($column, $value, $alias, $values)
    {
        $this->forIns[] = compact('column', 'value', 'alias', 'values');

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     * @param  string $column
     * @param  string $operator
     * @param  mixed $value
     * @param  string $boolean
     * @return \Illuminate\Database\Query\Builder|static
     * @throws \InvalidArgumentException
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column === '_id' || $column === 'id') {
            $column = $this->grammar->getMetaIdExpression($this);
        }
        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add a raw where clause to the query.
     * @param  string  $sql
     * @param  mixed   $bindings
     * @param  string  $boolean
     * @return $this
     */
    public function whereRaw($sql, $bindings = [], $boolean = 'and')
    {
        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean, 'bindings' => $bindings];

        $this->addBinding((array) $bindings, 'where');

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     * @param  string $column
     * @param  string $boolean
     * @param  bool $not
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereNull($column, $boolean = 'and', $not = false)
    {
        if ($column === '_id' || $column === 'id') {
            if ($not) {
                // the meta().id of a document is never null
                // so where condition "meta().id is not null" makes no changes to the result
                return $this;
            }
            $column = $this->grammar->getMetaIdExpression($this);
        }
        return parent::whereNull($column, $boolean, $not);
    }

    /**
     * Add a "where in" clause to the query.
     * @param  string $column
     * @param  mixed $values
     * @param  string $boolean
     * @return $this
     */
    public function whereAnyIn($column, $values, $boolean = 'and')
    {
        $type = 'AnyIn';

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        foreach ($values as $value) {
            if (!$value instanceof Expression) {
                $this->addBinding($value, 'where');
            }
        }

        return $this;
    }

    /**
     * Set custom options for the query.
     * @param  array $options
     * @return $this
     */
    public function options(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Handle dynamic method calls into the method.
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method == 'unset') {
            return call_user_func_array([$this, 'drop'], $parameters);
        }

        return parent::__call($method, $parameters);
    }
}
