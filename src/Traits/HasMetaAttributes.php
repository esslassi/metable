<?php

namespace Esslassi\Metable\Traits;

use Esslassi\Metable\Enums\MetaType;
use Esslassi\Metable\Models\Meta;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

trait HasMetaAttributes
{
	/**
	 * Returns either the default value or null if default isn't set
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getDefaultMetaValue($key, $default = null) : mixed {
		if ( property_exists( $this, 'defaultMetaValues' ) && array_key_exists( $key, $this->defaultMetaValues ) ) {
			return $this->defaultMetaValues[$key];
		} else {
			return $default;
		}
	}

    public function getAllMeta() {
        return $this->getSelectedMetas();
    }

	public function getMeta($key = null, $default = null) {
		if ( is_string( $key ) && preg_match( '/[,|]/is', $key ) ) {
			$key = preg_split( '/ ?[,|] ?/', $key );
		}
		return $this->{'getMeta' . ucfirst( strtolower( gettype( $key ) ) )}( $key, $default );
	}

	protected function getMetaString($key, $default = null) {
		$key = strtolower( $key );
		$meta = $this->metaList()->get( $key );
		
		if ( is_null( $meta ) || $meta->isMarkedForDeletion() ) {
			// Default values set in defaultMetaValues property take precedence over default value passed to this method
			return $this->getDefaultMetaValue( $key, $default );
		}
		
		return $meta->value;
	}
	
	protected function getMetaArray($keys, $default = null): BaseCollection {
		$collection = new BaseCollection();
		$metas = $this->getSelectedMetas($keys);
		$metas->each(function($meta, $key) use ($collection, $default) {
			$key = strtolower( $key );
			if ( $this->hasMeta( $key ) ) {
				if ( ! $meta->isMarkedForDeletion() ) {
					$collection->put( $key, $meta->value );
                    return true;
				}
			}
			// Key does not exist, so it's value will be the default value
			// Default values set in defaultMetaValues property take precedence over default value passed to this method
			$defaultValue = $this->getDefaultMetaValue( $key, $default );
			if ( is_null( $defaultValue ) ) {
				if ( is_array( $default ) ) {
					$defaultValue = $default[$key] ?? null;
				}
				else {
					$defaultValue = $default;
				}
			}
			
			$collection->put( $key, $defaultValue );
		});
		
		return $collection;
	}

	protected function getMetaNull(): BaseCollection {
		/** @noinspection PhpUnusedLocalVariableInspection */
		list( $keys, $raw ) = func_get_args();
		
		$collection = new BaseCollection();
		
		foreach ($this->metaList() as $meta) {
			if ( ! $meta->isMarkedForDeletion() ) {
				$collection->put( $meta->key, $raw ? $meta : $meta->value );
			}
		}
		
		return $collection;
	}

    protected function getSelectedMetas($keys = []) {
        return (count($keys) > 0) ? $this->metaList()->whereIn('key', $keys) : $this->metaList();
    }
    
    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
	public function getAttribute($key) {

		// Should call parent first.
		if ( ($value = parent::getAttribute( $key )) !== null ) {
			return $value;
		}
		
		// If fluent option is disabled we wont get meta value.
		if ( property_exists( $this, 'disableFluentMeta' ) && $this->disableFluentMeta ) {
			return $value;
		}
		
		// It is possible that attribute exists, or it has a cast, but it's null, so we check for that
		if ( array_key_exists( $key, $this->attributes ) ||
			array_key_exists( $key, $this->casts ) ||
			$this->hasGetMutator( $key ) ||
			$this->hasAttributeMutator( $key ) ||
			$this->isClassCastable( $key ) ) {
			return $value;
		}
		
		// If key is a relation name, then return parent value.
		// The reason for this is that it's possible that the relation does not exist and parent call returns null for that.
		if ( $this->isRelation( $key ) && $this->relationLoaded( $key ) ) {
			return $value;
		}
		
		// there was no attribute on the model
		// retrieve the data from meta relationship
		$meta = $this->getMeta( $key );
		
		// Check for meta accessor
		$accessor = Str::camel( 'get_' . $key . '_meta' );
		
		if ( method_exists( $this, $accessor ) ) {
			return $this->{$accessor}( $meta );
		}

        if (
            $meta == null && 
            property_exists( $this, 'defaultMetaValues' ) &&
			array_key_exists( $key, $this->defaultMetaValues )
        ) {
            return $this->defaultMetaValues[$key];
        }

		return $meta;
	}

    public function getMetableTable() {
        $defaultTable = config('metable.tables.default', 'meta');
        return $this->metableTable ?: $defaultTable;
    }
		
	/**
	 * Return the foreign key name for the meta table.
	 *
	 * @return string
	 */
	public function getMetaKeyName(): string {
		return $this->hasCustomMetable() ? Str::singular($this->getTable()) . '_id' : 'metable_id';
	}

	protected function getModelStub() {
		// get new meta model instance
		$model = new Meta();
		$model->setTable( $this->getMetableTable() );
		
		// model fill with attributes.
		if ( func_num_args() > 0 ) {
			array_filter( func_get_args(), [$model, 'fill'] );
		}
		
		return $model;
	}
	
	/**
	 * Check if an attribute is not metable
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
    public function isNotMetableAttribute($key, $value = null) {
        // First we will check for the presence of a mutator
		// or if key is a model attribute or has a column named to the key
		if ( $this->hasSetMutator( $key ) ||
            $this->hasAttributeSetMutator( $key ) ||
            $this->isEnumCastable( $key ) ||
            $this->isClassCastable( $key ) ||
            (! is_null( $value ) && $this->isJsonCastable( $key )) ||
            str_contains( $key, '->' ) ||
            $this->hasColumn( $key ) ||
            array_key_exists( $key, parent::getAttributes() )
        ) {
            return true;
        }

        return false;
    }
    
	function hasCustomMetable() : bool {
        $custom = config('metable.tables.custom', []);
		return ! empty( $custom[$this->getTable()] );
	}

	/**
	 * Determine if model table has a given column.
	 *
	 * @param string   $column
	 *
	 * @return boolean
	 */
	public function hasColumn($column): bool {
		static $columns;
		$class = get_class( $this );
		if ( ! isset( $columns[$class] ) ) {
			$columns[$class] = $this->getConnection()->getSchemaBuilder()->getColumnListing( $this->getTable() );
			if ( empty( $columns[$class] ) ) {
				$columns[$class] = [];
			}
			$columns[$class] = array_map(
				'strtolower',
				$columns[$class]
			);
		}
		return in_array( strtolower( $column ), $columns[$class] );
	}

	/**
	 * Determine if the meta or any of the given metas have been modified.
	 *
	 * @param array|string|null $metas
	 * @return bool
	 */
	public function isMetaDirty(...$metas): bool {
		if ( empty( $metas ) ) {
			foreach ($this->metaList() as $meta) {
				if ( $meta->isDirty() ) {
					return true;
				}
			}
			return false;
		}
		if ( is_array( $metas[0] ) ) {
			$metas = $metas[0];
		} elseif ( is_string( $metas[0] ) && preg_match( '/[,|]/is', $metas[0] ) ) {
			$metas = preg_split( '/ ?[,|] ?/', $metas[0] );
		}
		
		foreach ($metas as $meta) {
			if ( $this->metaList()->has( $meta ) ) {
				if ( $this->metaList()[$meta]->isDirty() ) {
					return true;
				}
				if ( $this->metaList()[$meta]->isMarkedForDeletion() ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Check if meta has default value
	 * @param string $key
	 * @return bool
	 */
	public function hasDefaultMetaValue($key): bool {
		if ( property_exists( $this, 'defaultMetaValues' ) ) {
			return array_key_exists( $key, $this->defaultMetaValues );
		}
		return false;
	}

	public function hasMeta($key, $deletion = false): bool {
		if ( is_string( $key ) && preg_match( '/[,|]/is', $key ) ) {
			$key = preg_split( '/ ?[,|] ?/', $key );
		}
		return $this->{'hasMeta' . ucfirst( gettype( $key ) )}( $key, $deletion );
	}
	
	protected function hasMetaString($key, $deletion = false): bool {
		$key = strtolower( $key );
		if ( $this->metaList()->has( $key ) ) {
			return $deletion || ! $this->metaList()[$key]->isMarkedForDeletion();
		}
		return false;
	}
	
	protected function hasMetaArray($keys, $deletion = false): bool {
		foreach ($keys as $key) {
			if ( ! $this->hasMeta( $key, $deletion ) ) {
				return false;
			}
		}
		return true;
	}
	/**
	 * Set attributes for the model
	 *
	 * @param array $attributes
	 *
	 * @return $this
	 */
	public function setAttributes(array $attributes) {
		foreach ($attributes as $key => $value) {
			$this->{$key} = $value;
		}
        return $this;
	}

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value) {
		// Don't set meta data if fluent access is disabled.
		if ( property_exists( $this, 'disableFluentMeta' ) && $this->disableFluentMeta ) {
			return parent::setAttribute( $key, $value );
		}
		// First we will check for the presence of a mutator
		// or if key is a model attribute or has a column named to the key
		if ( $this->isNotMetableAttribute($key) ) {
			return parent::setAttribute( $key, $value );
		}
		
		// If the key has a mutator execute it
		$mutator = Str::camel( 'set_' . $key . '_meta' );
		
		if ( method_exists( $this, $mutator ) ) {
			return $this->{$mutator}( $value );
		}
		
		// Key doesn't belong to model, lets create a new meta
		return $this->setMeta( $key, $value );
	}

    /**
     * Set a given meta on the model.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     * @return mixed
     */
	public function setMeta($key, $value = null) {
		return $this->{'setMeta' . ucfirst( gettype( $key ) )}( $key, $value );
	}
	
	protected function setMetaString($key, $value) {
		$key = strtolower( $key );
		
		// If there is a default value, remove the meta row instead - future returns of
		// this value will be handled via the default logic in the accessor
		if (
			property_exists( $this, 'defaultMetaValues' ) &&
			array_key_exists( $key, $this->defaultMetaValues ) &&
			$this->defaultMetaValues[$key] == $value &&
            method_exists( $this, 'removeMeta' )
		) {
			$this->removeMeta( $key );
			
			return $this;
		}
		
		if ( $this->hasMeta( $key, true ) ) {
			// Make sure deletion marker is not set
			$this->metaList()[$key]->markForDeletion( false );
			
			$this->metaList()[$key]->value = $value;
			
			return $this->metaList()[$key];
		}
        
		return $this->metaList()[$key] = $this->getModelStub( [
			'key' => $key,
			'value' => $value,
		] );
	}
	
	protected function setMetaArray(): Collection {
		list( $metas ) = func_get_args();
		
		$collection = new Collection();
		
		foreach ($metas as $key => $value) {
			$collection[] = $this->setMetaString( $key, $value );
		}
		
		return $collection;
	}

    /**
     * Alias of createMeta.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function addMeta($key, $value = MetaType::META_NOVAL) {
        return $this->createMeta($key, $value);
    }

    /**
     * Alias of updateMeta.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function editMeta($key, $value = MetaType::META_NOVAL) {
        return $this->updateMeta($key, $value);
    }

    /**
     * Create a new meta.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function createMeta($key, $value = MetaType::META_NOVAL) {
        return $this->setMetaString($key, $value);
    }

    /**
     * Update a given meta.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function updateMeta($key, $value = MetaType::META_NOVAL) {
        return $this->setMetaString($key, $value);
    }

    /**
     * Increase a numeric meta.
     *
     * @param  string  $key
     * @param  double|int  $num
     * @return mixed
     */
    public function increaseMeta($key, $num) {
        return $this->increaseOrDecreaseMeta($key, $num);
    }

    /**
     * Decrease a numeric meta.
     *
     * @param  string  $key
     * @param  double|int  $num
     * @return mixed
     */
    public function decreaseMeta($key, $num) {
        return $this->increaseOrDecreaseMeta($key, $num, 'decrease');
    }

    /**
     * Increase or decrease a numeric meta.
     *
     * @param  string  $key
     * @param  double|int  $num
     * @return mixed
     */
    public function increaseOrDecreaseMeta($key, $num, $type = 'increase') {
        $meta = $this->metaList()->get($key);

        if ($meta->type === MetaType::META_INTEGER || $meta->type === MetaType::META_DOUBLE) {
            $value =  $type == 'increase' ?
                $meta->value + $num :
                $meta->value - $num;

            return $this->setMetaString($key, $value);
        }

        return $this;
    }
    	
	/**
	 * remove Meta Data functions
	 * -------------------------.
	 */
	public function removeMeta($key) {
		return $this->{'removeMeta' . ucfirst( gettype( $key ) )}( $key );
	}
	
	protected function removeMetaString($key) {
		$key = strtolower( $key );
		if ( $this->metaList()->has( $key ) ) {
			$this->metaList()[$key]->markForDeletion();
		}
	}
	
	protected function removeMetaArray() {
		list( $keys ) = func_get_args();
		
		foreach ($keys as $key) {
			$key = strtolower( $key );
			$this->removeMetaString( $key );
		}
	}

    public function deleteAllMeta() {
        return (bool) $this->metaQuery()->delete();
    }

	public function __unset($key) {
		// unset attributes and relations
		parent::__unset( $key );
		
		// Don't unset meta data if fluent access is disabled.
		if ( property_exists( $this, 'disableFluentMeta' ) && $this->disableFluentMeta ) {
			return;
		}
		
		// delete meta, only if pivot-prefix is not detected in order to avoid unnecessary (N+1) queries
		// since Eloquent tries to "unset" pivot-prefixed attributes in m2m queries on pivot tables.
		// N.B. Regular unset of pivot-prefixed keys is thus compromised.
		if ( strpos( $key, 'pivot_' ) !== 0 ) {
			$this->removeMeta( $key );
		}
	}
}
