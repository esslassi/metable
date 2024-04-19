<?php

namespace Esslassi\Metable\Traits;

use Illuminate\Database\Eloquent\Collection;
use Esslassi\Metable\Models\Meta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMetaRelationship
{
    protected $__metaList = null;

    public function metas() : MorphMany {
        $instance = new Meta();
        // Here we will gather up the morph type and ID for the relationship so that we
        // can properly query the intermediate table of a relation. Finally, we will
        // get the table and create the relationship instances for the developers.
        [$type, $id] = $this->getMorphs('metable', null, null);

        $table = $instance->getTable();

        $localKey = $this->getKeyName();

        return new MorphMany($instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $localKey);
    }

    public static function metaRelationName()
    {
        return 'metas';
    }

    public function scopeWithMeta(Builder $query, $callback = null)
    {
        $relation = static::metaRelationName();

        if ($callback) {
            return $query->with([$relation => $callback]);
        }
        
        return $query->with($relation);
    }

    public function metaQuery() {
        $relation = static::metaRelationName();
        return $this->{$relation}();
    }

    public function metaList() {
        if ( is_null( $this->__metaList ) ) {
			if ( $this->exists && ! is_null( $this->metas ) ) {
				$this->__metaList = $this->metas->keyBy( 'key' );
			} else {
				$this->__metaList = new Collection();
			}
		}
		return $this->__metaList;
    }

    public function updateMetaList($updatedList) {
        $relation = static::metaRelationName();
        $this->{$relation} = $updatedList;
    }
}
