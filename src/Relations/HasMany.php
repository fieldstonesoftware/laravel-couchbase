<?php

namespace Fieldstone\Couchbase\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Support\Arr;

class HasMany extends EloquentHasMany
{

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            if($this->foreignKey === '_id') {
                $this->query->useKeys(is_array($this->getParentKey()) ? $this->getParentKey() : [$this->getParentKey()]);
            } else {
                $this->query->where($this->foreignKey, '=', $this->getParentKey());
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        if($this->foreignKey === '_id') {
            $this->query->useKeys(Arr::flatten($this->getKeys($models, $this->localKey)));
        } else {
            $this->query->whereIn(
                $this->foreignKey, $this->getKeys($models, $this->localKey)
            );
        }
    }

    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    public function getPlainForeignKey()
    {
        return $this->getForeignKeyName();
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKeyName();
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getHasCompareKey();

        return $query->select($foreignKey);
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  \Illuminate\Database\Eloquent\Builder $parent
     * @param  array|mixed $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationQuery(Builder $query, Builder $parent, $columns = ['*'])
    {
        $query->select($columns);

        $key = $this->wrap($this->getQualifiedParentKeyName());

        return $query->whereNotNull($this->getHasCompareKey());
    }

    /**
     * @param array $models
     * @param Collection $results
     * @param string $relation
     * @param string $type
     * @return array
     */
    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $keys = $model->getAttribute($this->localKey);
            if (is_array($keys)) {
                $result = collect();
                foreach ($keys as $key) {
                    if (isset($dictionary[$key])) {
                        if (!is_array($dictionary[$key])) {
                            $result[] = $dictionary[$key];
                            continue;
                        }
                        foreach ($dictionary[$key] as $object) {
                            $result[] = $object;
                        }
                    }
                }
                $model->setRelation($relation, $result);
            } elseif (isset($dictionary[$keys])) {
                $model->setRelation(
                    $relation, $this->getRelationValue($dictionary, $keys, $type)
                );
            }
        }

        return $models;
    }

}
