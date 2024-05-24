<?php

namespace Esslassi\Metable\Traits;

use Esslassi\Metable\Models\Meta;
use Esslassi\Metable\Relations\MetaOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasMetaRelationships
{

    /**
     * Define a has-meta-one relationship.
     *
     * @param  string  $related
     * @param  string|null  $metaKey
     * @param  string|null  $localKey
     * @param  string|null  $secondLocalKey
     * @return \Esslassi\Metable\Relations\MetaOne
     */
    public function hasMetaOne($related, $metaKey = null, $localKey = null, $secondLocalKey = null)
    {
        $through = $this->newRelatedMetaThroughInstance(Meta::class);

        $firstKey = 'metable_id';

        $secondKey = 'id';

        return $this->newHasMetaOne(
            $this->newRelatedMetaInstance($related)->newQuery(), $this, $through,
            $metaKey, $firstKey, $secondKey, $localKey ?: $this->getKeyName(),
            $secondLocalKey ?: $through->getThroughKeyName()
        );
    }

    /**
     * Instantiate a new MetaOne relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $farParent
     * @param  \Illuminate\Database\Eloquent\Model  $throughParent
     * @param  string  $metaKey
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return \Esslassi\Metable\Relations\MetaOne
     */
    protected function newHasMetaOne(Builder $query, Model $farParent, Model $throughParent, $metaKey, $firstKey, $secondKey, $localKey, $secondLocalKey)
    {
        return new MetaOne($query, $farParent, $throughParent, $metaKey, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Create a new model instance for a related model.
     *
     * @param  string  $class
     * @return mixed
     */
    protected function newRelatedMetaInstance($class)
    {
        return tap(new $class, function ($instance) {
            if (! $instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Create a new model instance for a related "through" model.
     *
     * @param  string  $class
     * @return mixed
     */
    protected function newRelatedMetaThroughInstance($class)
    {
        return new $class;
    }
}
