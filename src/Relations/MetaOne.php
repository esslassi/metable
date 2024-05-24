<?php

namespace Esslassi\Metable\Relations;

use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class MetaOne extends HasOneThrough
{

    /**
     * The meta key on the relationship.
     *
     * @var string
     */
    protected $metaKey;

    /**
     * Create a new has many through relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $farParent
     * @param  \Illuminate\Database\Eloquent\Model  $throughParent
     * @param  string  $metaKey
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return void
     */
    public function __construct(Builder $query, Model $farParent, Model $throughParent, $metaKey, $firstKey, $secondKey, $localKey, $secondLocalKey)
    {
        $this->metaKey = $metaKey;

        parent::__construct($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }
    
    /**
     * Set the join clause on the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     * @return void
     */
    protected function performJoin(Builder $query = null)
    {
        $query = $query ?: $this->query;

        $farKey = $this->getQualifiedFarKeyName();

        $parKey = $this->getQualifiedParentKeyName();

        $metaKey = $this->metaKey;

        $query->join($this->throughParent->getTable(), function(JoinClause $join) use ($farKey, $parKey, $metaKey) {
            $join->on($parKey, '=', $farKey)->where($this->getQualifiedThroughMetaKeyName(), $metaKey);
        });

        if ($this->throughParentSoftDeletes()) {
            $query->withGlobalScope('SoftDeletableHasManyThrough', function ($query) {
                $query->whereNull($this->throughParent->getQualifiedDeletedAtColumn());
            });
        }
    }

    /**
     * Get the fully qualified meta key name.
     *
     * @return string
     */
    public function getQualifiedThroughMetaKeyName()
    {
        return $this->throughParent->qualifyColumn('key');
    }

    /**
     * Delete records from the database.
     *
     * @return mixed
     */
    public function delete()
    {
        $delete = parent::delete();

        if( $delete && method_exists($this->farParent, 'removeMeta') ) {
            try {
                $this->farParent->removeMeta($this->metaKey);
                $this->farParent->save();
            } catch (\Throwable $th) {}
        }

        return $delete;
    }

    /**
     * Save a new model and return the instance.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model|$this
     */
    public function create(array $attributes = [])
    {
        return tap($this->newModelInstance($attributes), function ($instance) {

            DB::beginTransaction();

            $instance->saved(function($model) {
                try {
                    $this->prepareThroughParentForCreate($model)->save();
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                }
            });

            $instance->save();
        });
    }

    /**
     * Prepare ThroughParent for create and return the instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model|$this
     */
    protected function prepareThroughParentForCreate(Model $model) {
        $this->throughParent->key = $this->metaKey;
        $this->throughParent->{$this->firstKey} = $this->farParent->getKey();
        $this->throughParent->metable_type = get_class($model);
        $this->throughParent->{$this->throughParent->getThroughKeyName()} = $model->getKey();
        return $this->throughParent;
    }
}
