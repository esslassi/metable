<?php

namespace Esslassi\Metable\Traits;

use Esslassi\Metable\Enums\MetaType;
use Illuminate\Database\Eloquent\Builder;

trait HasGlobalMetaScopes
{
    private $countOfMetaJoins = 0;

    /**
     * Order by meta.
     *
     * @param  Builder       $query
     * @param  string        $key
     * @param  'asc'|'desc'  $direction
     * @return Builder
     */
    public function scopeOrderByMeta(Builder $query, $key, $direction = 'asc')
    {
        $this->countOfMetaJoins += 1;

        $table = $this->getMetableTable();

        return $query->leftJoin($table . ' as meta' . $this->countOfMetaJoins, function(Builder $q) use ($key) {
            $q->on('meta' . $this->countOfMetaJoins . '.metable_id', '=', $this->getTable() . ".id");
            $q->where('meta' . $this->countOfMetaJoins . '.metable_type', '=', static::class);
            $q->where('meta' . $this->countOfMetaJoins . '.key', $key);
        })->orderByRaw("CASE (meta" . $this->countOfMetaJoins . ".key)
              WHEN '$key' THEN 1
              ELSE 0
              END
              DESC")
        ->orderBy('meta' . $this->countOfMetaJoins . '.value', strtoupper($direction))
        ->select($this->getTable() . ".*");
    }

    /**
     * whereMeta scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  string    $operator
     * @param  mixed     $value
     * @return Builder
     */
    public function scopeWhereMeta(Builder $query, $key, $operator = null, $value = MetaType::META_NOVAL)
    {
        return $this->whereMetaProccess($query, $key, $operator, $value);
    }

    /**
     * orWhereMeta scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  string    $operator
     * @param  mixed     $value
     * @return Builder
     */
    public function scopeOrWhereMeta(Builder $query, $key, $operator = null, $value = MetaType::META_NOVAL)
    {
        return $this->whereMetaProccess($query, $key, $operator, $value, true);
    }

    /**
     * A proccess method for whereMeta scopes.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  string    $operator
     * @param  mixed     $value
     * @param  bool      $orWhere
     * @return Builder
     */
    private function whereMetaProccess(Builder $query, $key, $operator, $value, $orWhere = false)
    {
        $methodType = $orWhere ? 'orWhereHas' : 'whereHas';

        $relation = static::metaRelationName();

        if (is_array($key)) {

            $conditions = $key;

            foreach ($conditions as $condition) {

                if(!is_array($condition)) {

                    list($conditionKey, $conditionOperator, $conditionValue) = $this->extractKeyOperatorValue($conditions);

                    $query = call_user_func_array([$this, 'scopeWhereMetaTest'], [$query, $conditionKey, $conditionOperator, $conditionValue, $orWhere]);

                    continue;
                }

                list($conditionKey, $conditionOperator, $conditionValue) = $this->extractKeyOperatorValue($condition);

                $query = call_user_func_array([$this, 'scopeWhereMetaTest'], [$query, $conditionKey, $conditionOperator, $conditionValue, $orWhere]);
            }
        }

        return $query->{$methodType}($relation, function(Builder $metaQuery) use($key, $value, $operator){
            if ($value === MetaType::META_NOVAL) {
                $value = $operator;
                $operator = '=';
            }

            $metaQuery->where('key', $key)->where('value', $operator, $value);
        });
    }

    /**
     * Extract key operator value.
     *
     * @param  mixed     $condition
     * @return array
     */
    private function extractKeyOperatorValue($condition)
    {
        $key = $condition[0];

        $operator = array_key_exists(2, $condition) ? $condition[1] : '=';

        $value = array_key_exists(2, $condition) ? $condition[2] : $condition[1];

        return [$key, $operator, $value];
    }

    /**
     * whereMetaIn scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  array     $values
     * @return Builder
     */
    public function scopeWhereMetaIn(Builder $query, $key, $values)
    {
        return $this->whereMetaInProccess($query, $key, $values, true);
    }

    /**
     * whereMetaNotIn scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  array     $values
     * @return Builder
     */
    public function scopeWhereMetaNotIn(Builder $query, $key, $values) {
        return $this->whereMetaInProccess($query, $key, $values, false);
    }

    /**
     * A proccess method for whereMetaIn and whereMetaNotIn scopes.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  array     $values
     * @param  bool      $in
     * @return Builder
     */
    public function whereMetaInProccess(Builder $query, $key, $values, $in = true) {
        $relation = static::metaRelationName();

        $methodType = $in ? 'whereIn' : 'whereNotIn';

        return $query->whereHas($relation, function(Builder $query) use ($methodType, $key, $values, $in) {
            if ($in) {
                return $query->where('key', $key)->whereIn('value', $values);
            }

            $query->where('key', $key)->{$methodType}('value',  $values);
        });
    }

    /**
     * whereMetaNull scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @return Builder
     */
    public function scopeWhereMetaNull(Builder $query, $key)
    {
        $this->whereMetaNullProccess($query, $key);
    }

    /**
     * orWhereMetaNull scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @return Builder
     */
    public function scopeOrWhereMetaNull(Builder $query, $key)
    {
        return $this->whereMetaNullProccess($query, $key, true);
    }

    /**
     * whereMetaNotNull scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @return Builder
     */
    public function scopeWhereMetaNotNull(Builder $query, $key)
    {
        $this->whereMetaNullProccess($query, $key, false, '<>');
    }

    /**
     * orWhereMetaNotNull scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @return Builder
     */
    public function scopeOrWhereMetaNotNull(Builder $query, $key)
    {
        return $this->whereMetaNullProccess($query, $key, true, '<>');
    }

    /**
     * A proccess method for whereMetaNull, orWhereMetaNull, whereMetaNotNull and orWhereMetaNotNull scopes.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  bool      $orWhere
     * @param  string    $operator
     * @return Builder
     */
    private function whereMetaNullProccess(Builder $query, $key, $orWhere = false, $operator = '=')
    {
        $relation = static::metaRelationName();

        $methodType = $orWhere ? 'orWhereHas' : 'whereHas';

        return $query->{$methodType}($relation, function(Builder $query) use ($key, $operator) {
            $query->where('key', $key)->where('type', $operator, MetaType::META_NULL);
        });
    }

    /**
     * whereMetaHas scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  bool      $countNull
     * @return Builder
     */
    public function scopeWhereMetaHas(Builder $query, $key = null, $countNull = false)
    {
        $this->whereMetaHasProccess($query, $key, $countNull);
    }

    /**
     * orWhereMetaHas scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  bool      $countNull
     * @return Builder
     */
    public function scopeOrWhereMetaHas(Builder $query, $key = null, $countNull = false)
    {
        $this->whereMetaHasProccess($query, $key, $countNull, true);
    }

    /**
     * A proccess method for whereMetaHas and orWhereMetaHas scopes.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  bool      $countNull
     * @param  bool      $orWhere
     * @return Builder
     */
    private function whereMetaHasProccess(Builder $query, $key, $countNull, $orWhere = false)
    {
        $relation = static::metaRelationName();

        $methodType = $orWhere ? 'orWhereHas' : 'whereHas';

        if ($key === null) {
            return $query->{$methodType}($relation);
        }

        return $query->{$methodType}($relation, function(Builder $query) use ($key, $countNull) {
            $query->where('key', $key);
            
            if (!$countNull) {
                $query->where('type', '<>', MetaType::META_NULL);
            }
        });
    }

    /**
     * whereMetaDoesntHave scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  bool      $countNull
     * @return Builder
     */
    public function scopeWhereMetaDoesntHave(Builder $query, $key = null, $countNull = false)
    {
        $this->whereMetaDoesntHaveProccess($query, $key, $countNull);
    }

    /**
     * orWhereMetaDoesntHave scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  bool      $countNull
     * @return Builder
     */
    public function scopeOrWhereMetaDoesntHave(Builder $query, $key = null, $countNull = false)
    {
        $this->whereMetaDoesntHaveProccess($query, $key, $countNull, true);
    }

    /**
     * A proccess method for whereMetaDoesntHave and orWhereMetaDoesntHave scopes.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  bool      $countNull
     * @param  bool      $orWhere
     * @return Builder
     */
    public function whereMetaDoesntHaveProccess(Builder $query, $key, $countNull, $orWhere = false)
    {
        $relation = static::metaRelationName();

        $methodType = $orWhere ? 'orWhereDoesntHave' : 'whereDoesntHave';

        if ($key === null) {
            return $query->{$methodType}($relation);
        }

        return $query->{$methodType}($relation, function(Builder $query) use ($key, $countNull) {
            $query->where('key', $key);
            
            if ($countNull) {
                $query->where('type', '<>', MetaType::META_NULL);
            }
        });
    }

    /**
     * whereMetaColumnIn scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  mixed     $value
     * @return Builder
     */
    public function scopeWhereMetaColumnIn(Builder $query, $key, $value)
    {
        return $this->whereMetaColumnInProccess($query, $key, $value, true, false);
    }

    /**
     * whereMetaColumnNotIn scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  mixed     $value
     * @return Builder
     */
    public function scopeWhereMetaColumnNotIn(Builder $query, $key, $value) {
        return $this->whereMetaColumnInProccess($query, $key, $value, false, false);
    }

    /**
     * orWhereMetaColumnIn scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  mixed     $value
     * @return Builder
     */
    public function scopeOrWhereMetaColumnIn(Builder $query, $key, $value)
    {
        return $this->whereMetaColumnInProccess($query, $key, $value, true, true);
    }

    /**
     * orWhereMetaColumnNotIn scope for query.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  mixed     $value
     * @return Builder
     */
    public function scopeOrWhereMetaColumnNotIn(Builder $query, $key, $value) {
        return $this->whereMetaColumnInProccess($query, $key, $value, false, true);
    }

    /**
     * A proccess method for whereMetaColumnIn and whereMetaColumnNotIn scopes.
     *
     * @param  Builder   $query
     * @param  string    $key
     * @param  mixed     $value
     * @param  bool      $in
     * @param  bool      $orWhere
     * @return Builder
     */
    public function whereMetaColumnInProccess(Builder $query, $key, $value, $in = true, $orWhere = false) {
        $relation = static::metaRelationName();

        $methodType = $orWhere ? 'orWhereHas' : 'whereHas';

        $expression = ($in ? '' : 'NOT ') . "FIND_IN_SET(?, REGEXP_REPLACE(`value`, '[\\[\\]\\s]+', ''))";
        
        return $query->{$methodType}($relation, function(Builder $query) use ($expression, $key, $value) {
            $query->where('key', $key)->where('type', MetaType::META_ARRAY)->whereRaw($expression, [$value]);
        });
    }
}
