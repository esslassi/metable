<?php

namespace Esslassi\Metable;

use Esslassi\Metable\Traits\HasMetaAttributes;
use Esslassi\Metable\Traits\HasMetaEvents;
use Esslassi\Metable\Traits\HasMetaRelationship;
use Illuminate\Database\Eloquent\Concerns\HasGlobalScopes;
use Illuminate\Support\Arr;

trait Metable {
    use HasMetaAttributes,
		HasMetaEvents,
		HasMetaRelationship,
		HasGlobalScopes;

    public function meta($key = null, $default = null)
    {
        if (is_array($key) && Arr::isAssoc($key)) {
            return $this->setMeta($key);
        }

        if ($key === null) {
            return $this->getAllMeta();
        }

        return $this->getMeta($key, $default);
    }

	public function saveMeta() {
		foreach ($this->metaList() as $meta) {
			$meta->setTable( $this->getMetableTable() );
			
			if ( $meta->isMarkedForDeletion() ) {
				if ( $meta->exists ) {
					if ( $this->fireMetaEvent( 'deleting', $meta->key ) === false ) {
						continue;
					}
				}
				$meta->delete();
				unset( $this->metaList()[$meta->key] );
				$this->fireMetaEvent( 'deleted', $meta->key, false );
				continue;
			}
			
			if ( $meta->isDirty() ) {
				if ( $this->fireMetaEvent( 'saving', $meta->key ) === false ) {
					continue;
				}
				if ( $meta->exists ) {
					if ( $this->fireMetaEvent( 'updating', $meta->key ) === false ) {
						continue;
					}
					$nextEvent = 'updated';
				} else {
					if ( $this->fireMetaEvent( 'creating', $meta->key ) === false ) {
						continue;
					}
					$nextEvent = 'created';
				}
				// set meta and model relation id's into meta table.
				$meta->setAttribute( $this->getMetaKeyName(), $this->getKey() );
				if( !$this->hasCustomMetable() ) {
					$meta->setAttribute( 'metable_type', $this->getMorphClass() );
				}
				if ( $meta->save() ) {
					$this->fireMetaEvent( $nextEvent, $meta->key, false );
					$this->fireMetaEvent( 'saved', $meta->key, false );
				}
			}
		}
		
		if ( $this->__wasCreatedEventFired ) {
			$this->__wasCreatedEventFired = false;
			$this->fireModelEvent( 'createdWithMetas', false );
		}
		
		if ( $this->__wasUpdatedEventFired ) {
			$this->__wasUpdatedEventFired = false;
			$this->fireModelEvent( 'updatedWithMetas', false );
		}
		
		if ( $this->__wasSavedEventFired ) {
			$this->__wasSavedEventFired = false;
			$this->fireModelEvent( 'savedWithMetas', false );
		}
	}

	public static function bootMetable() {
		static::saved( function ($model) {
			$model->__wasSavedEventFired = true;
			$model->saveMeta();
		} );
		
		static::created( function ($model) {
			$model->__wasCreatedEventFired = true;
		} );
		
		static::updated( function ($model) {
			$model->__wasUpdatedEventFired = true;
		} );

        static::deleted(function ($model) {
            $model->deleteAllMeta();            
        });
	}
	
	protected function initializeMetable() {
		$this->observables = array_merge( $this->observables, [
			'createdWithMetas',
			'updatedWithMetas',
			'savedWithMetas',
		] );
		$this->observables = array_unique( $this->observables );
	}
	
	public static function createdWithMetas($callback) {
		static::registerModelEvent( 'createdWithMetas', $callback );
	}
	
	public static function updatedWithMetas($callback) {
		static::registerModelEvent( 'updatedWithMetas', $callback );
	}
	
	public static function savedWithMetas($callback) {
		static::registerModelEvent( 'savedWithMetas', $callback );
	}
	
	/**
	 * Convert the model instance to an array.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return (property_exists( $this, 'hideMeta' ) && $this->hideMeta) ?
			parent::toArray() : 
			(( property_exists( $this, 'disableFluentMeta' ) && $this->disableFluentMeta ) ?
			array_merge( parent::toArray(), [
				'meta_data' => $this->getMeta()->toArray(),
			] ) : array_merge( parent::toArray(), $this->getMeta()->toArray() ));
	}
}