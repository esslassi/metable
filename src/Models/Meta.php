<?php
namespace Esslassi\Metable\Models;

use Illuminate\Database\Eloquent\Model;
use Esslassi\Metable\Enums\MetaType;

class Meta extends Model
{
    public $isModelSaving;

    protected $originalValue;

    protected $guarded = ['id'];
	/**
	 * Whether or not to delete the Data on save.
	 *
	 * @var bool
	 */
	protected $markForDeletion = false;

    public function __construct(array $attributes = []) {
        $tableName = config('metable.tables.default', 'meta');

        parent::__construct($attributes);

        $this->setTable($tableName);
    }

    /**
     * Get the parent meta model (ex: Post, User, etc..).
     */
    public function metable() {
        return $this->morphTo();
    }

    public function getValueAttribute($value) {
        return MetaType::decode($value, $this->type);
    }

	/**
	 * Whether or not to delete the Data on save.
	 *
	 * @param bool $bool
	 */
	public function markForDeletion(bool $bool = true) {
		$this->markForDeletion = $bool;
	}
	
	/**
	 * Check if the model needs to be deleted.
	 *
	 * @return bool
	 */
	public function isMarkedForDeletion(): bool {
		return $this->markForDeletion;
	}
    
    /**
	 * Set the value and type.
	 *
	 * @param $value
	 */
	public function setValueAttribute($value) {
        $this->type = MetaType::guessType($value);
        $this->attributes['value'] = MetaType::encode($value);
    }
}