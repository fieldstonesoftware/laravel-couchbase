<?php

namespace Fieldstone\Couchbase\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Fieldstone\Couchbase\KeyId;
use Fieldstone\Couchbase\Query\Builder as QueryBuilder;
use Fieldstone\Couchbase\Query\Grammar;
use Fieldstone\Couchbase\Relations\EmbedsMany;
use Fieldstone\Couchbase\Relations\EmbedsOne;
use Illuminate\Support\Str;

class Model extends BaseModel
{
    use HybridRelations;

    /**
     * The collection associated with the model.
     * @var string
     */
    protected $collection;

    /**
     * The primary key for the model.
     * @var string
     */
    protected $primaryKey = '_id';

    /**
     * The primary key type.
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The parent relation instance.
     * @var Relation
     */
    protected $parentRelation;

    /**
     * Custom accessor for the model's id.
     * Converts references to ->id into the Couchbase ->_id
     * @param  mixed $value
     * @return mixed
     */
    public function getIdAttribute($value = null)
    {
        // Allows $model->id to properly resolve to _id.
        if (!$value && array_key_exists('_id', $this->attributes)) {
            $value = $this->attributes['_id'];
        }

        return $value;
    }

    /**
     * Must be implemented to return the document type for this model.
     */
    public function getDocumentType()
    {
        return Str::snake(class_basename($this));
    }

    /**
     *
     */
    public function getTenantId(){
        return KeyId::AllTenantIndicator;
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
        return $this->getKeyName();
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Get the document type key for the model.
     *
     * @return string
     */
    public static function getDocumentTypeKeyName()
    {
        return config('couchbase.type_key');
    }

    /**
     * Get the tenant id key for the model.
     *
     * @return string
     */
    public static function getTenantIdKeyName()
    {
        return config('couchbase.tenant_id_key');
    }

    /**
     * Get the primary key for the model.
     *
     * @param null $type Document Type, Defaults to Snake Case Model Name
     * @param null $tenantId Tenant ID, Default to * (all tenants)
     * @return string
     * @throws \Exception if something goes wrong with generating a UUID
     */
    public function getNewKeyValue($type, $tenantId)
    {
        return KeyId::getNewId($type, $tenantId);
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  string $related
     * @param  string $localKey
     * @param  string $foreignKey
     * @param  string $relation
     * @return \Fieldstone\Couchbase\Relations\EmbedsMany
     */
    protected function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        if (is_null($localKey)) {
            $localKey = $relation;
        }

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsMany($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  string $related
     * @param  string $localKey
     * @param  string $foreignKey
     * @param  string $relation
     * @return \Fieldstone\Couchbase\Relations\EmbedsOne
     */
    protected function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        if (is_null($localKey)) {
            $localKey = $relation;
        }

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsOne($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    /**
     * Called when in an Eloquent query to get the table name for this.
     * The table name concept is warped a bit here by becoming the document type.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->getDocumentType();
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return null;
        }

        if ($key === $this->primaryKey && $key !== '_id') {
            $key = '_id';
        }

        // Dot notation support.
        if (Str::contains($key, '.') && in_array($key, $this->attributes)) {
            return $this->getAttributeValue($key);
        }

        // This checks for embedded relation support.
        if (method_exists($this, $key) && !method_exists(self::class, $key)) {
            return $this->getRelationValue($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        // Support keys in dot notation.
        if (Str::contains($key, '.')) {
            $attributes = Arr::dot($this->attributes);

            if (array_key_exists($key, $attributes)) {
                return $attributes[$key];
            }
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string $key
     * @param  mixed $value
     */
    public function setAttribute($key, $value)
    {
        if ($key === $this->primaryKey && $key !== '_id') {
            $key = '_id';
        }

        // Support keys in dot notation.
        if (Str::contains($key, '.')) {
            if (in_array($key, $this->getDates()) && $value) {
                $value = $this->fromDateTime($value);
            }

            Arr::set($this->attributes, $key, $value);

            return;
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        // Convert dot-notation dates.
        foreach ($this->getDates() as $key) {
            if (Str::contains($key, '.') and Arr::has($attributes, $key)) {
                Arr::set($attributes, $key, (string)$this->asDateTime(Arr::get($attributes, $key)));
            }
        }

        return $attributes;
    }

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        return $this->casts;
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed $columns
     * @return int
     */
    public function drop($columns)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        // Unset attributes
        foreach ($columns as $column) {
            $this->__unset($column);
        }

        // Perform unset only on current document
        return $this->newQuery()->useKeys([$this->getKey()])->unset($columns);
    }

    /**
     * Append one or more values to an array.
     *
     * @return mixed
     */
    public function push()
    {
        if ($parameters = func_get_args()) {
            $unique = false;

            if (count($parameters) == 3) {
                list($column, $values, $unique) = $parameters;
            } else {
                list($column, $values) = $parameters;
            }

            // Do batch push by default.
            if (!is_array($values)) {
                $values = [$values];
            }

            $query = $this->setKeysForSaveQuery($this->newQuery());

            $this->pushAttributeValues($column, $values, $unique);

            return $query->push($column, $values, $unique);
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  string $column
     * @param  mixed $values
     * @return mixed
     */
    public function pull($column, $values)
    {
        // Do batch pull by default.
        if (!is_array($values)) {
            $values = [$values];
        }

        $query = $this->setKeysForSaveQuery($this->newQuery());

        $this->pullAttributeValues($column, $values);

        return $query->pull($column, $values);
    }

    /**
     * Append one or more values to the underlying attribute value and sync with original.
     *
     * @param  string $column
     * @param  array $values
     * @param  bool $unique
     */
    protected function pushAttributeValues($column, array $values, $unique = false)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            // Don't add duplicate values when we only want unique values.
            if ($unique and in_array($value, $current)) {
                continue;
            }

            array_push($current, $value);
        }

        $this->attributes[$column] = $current;

        $this->syncOriginalAttribute($column);
    }

    /**
     * Remove one or more values to the underlying attribute value and sync with original.
     *
     * @param  string $column
     * @param  array $values
     */
    protected function pullAttributeValues($column, array $values)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            $keys = array_keys($current, $value);

            foreach ($keys as $key) {
                unset($current[$key]);
            }
        }

        $this->attributes[$column] = array_values($current);

        $this->syncOriginalAttribute($column);
    }

    /**
     * Set the parent relation.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $relation
     */
    public function setParentRelation(Relation $relation)
    {
        $this->parentRelation = $relation;
    }

    /**
     * Get the parent relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getParentRelation()
    {
        return $this->parentRelation;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Fieldstone\Couchbase\Query\Builder $query
     * @return \Fieldstone\Couchbase\Eloquent\Builder|static
     * @throws \Exception
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return QueryBuilder
     * @throws \Exception
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder(
            $connection, $connection->getQueryGrammar(), $connection->getPostProcessor()
        );
    }

    /**
     * Perform any actions that are necessary after the model is saved.
     *
     * @param  array $options
     * @return void
     */
    protected function finishSave(array $options)
    {
        $this->attributes = Grammar::removeMissingValue($this->attributes);

        $this->fireModelEvent('saved', false);

        $this->syncOriginal();

        if (Arr::get($options, 'touch', true)) {
            $this->touchOwners();
        }
    }

    /**
     * We just return original key here in order to support keys in dot-notation
     *
     * @param  string $key
     * @return string
     */
    protected function removeTableFromKey($key)
    {
        return $key;
    }

    /**
     * Destroy the models for the given IDs.
     *
     * @param  array|int $ids
     * @return int
     */
    public static function destroy($ids)
    {
        // We'll initialize a count here so we will return the total number of deletes
        // for the operation. The developers can then check this number as a boolean
        // type value or get this total count of records deleted for logging, etc.
        $count = 0;

        $ids = is_array($ids) ? $ids : func_get_args();

        $instance = new static;

        // We will actually pull the models from the database table and call delete on
        // each of them individually so that their events get fired properly with a
        // correct set of attributes in case the developers wants to check these.
        $key = $instance->getKeyName();

        foreach ($instance->whereIn($key, $ids)->get() as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @inheritdoc
     */
    public function getForeignKey()
    {
        return Str::snake(class_basename($this)) . '_' . ltrim($this->primaryKey, '_');
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Unset method
        if ($method == 'unset') {
            return call_user_func_array([$this, 'drop'], $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return bool
     * @throws \Exception
     */
    public function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        // set the document ID
        $this->setAttribute($this->getKeyName()
            , $this->getNewKeyValue($this->getDocumentType(), $this->getTenantId())
        );

        // set document type
        $this->setAttribute($this->getDocumentTypeKeyName(), $this->getDocumentType());

        // set tenant ID
        $this->setAttribute($this->getTenantIdKeyName(), $this->getTenantId());

        return parent::performInsert($query);
    }
}
