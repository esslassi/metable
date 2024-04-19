<?php

namespace Esslassi\Metable\Enums;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

enum MetaType: string
{
    case META_COLLECTION    = "collection";
    case META_MODEL         = "model";
    case META_OBJECT        = "object";
    case META_ARRAY 	    = "array";
    case META_JSON 		    = "json";
    case META_STRING 		= "string";
    case META_INTEGER 		= "integer";
    case META_DOUBLE 		= "double";
    case META_BOOLEAN 		= "boolean";
    case META_NULL          = "null";
    case META_NOVAL         = "NOVAL";

    public static function guessType($value)
    {
        $valueType = gettype($value);

        if ($value instanceof Collection) {
            return $value->count() ? static::META_COLLECTION->value : static::META_NULL->value;
        }

        if ($value instanceof Model) {
            return static::META_MODEL->value;
        }

        if ($valueType === 'object') {
            return static::META_OBJECT->value;
        }

        if ($valueType === 'array') {
            return $value === [] ? static::META_NULL->value : static::META_ARRAY->value;
        }

        if ($valueType === 'boolean') {
            return static::META_BOOLEAN->value;
        }

        if ($valueType === 'integer') {
            return static::META_INTEGER->value;
        }

        if ($valueType === 'double') {
            return static::META_DOUBLE->value;
        }

        if ($valueType === 'NULL') {
            return static::META_NULL->value;
        }

        if (static::isJson($value)) {
            $jsonData = json_decode($value, true);
            return empty($jsonData) ? static::META_NULL->value : static::META_JSON->value;
        }
        
        return empty($value) ? static::META_NULL->value : static::META_STRING->value;
    }

    public static function encode($data)
    {
        $type = static::guessType($data);

        if ( $type === static::META_COLLECTION->value ) {
            return json_encode( $data->toArray() );
        }

        if ( $type === static::META_OBJECT->value ) {
            return json_encode( $data );
        }

        if ($type === static::META_MODEL->value) {
			$class = get_class( $data );
			return $class . (! $data->exists ? '' : '#' . $data->getKey());
        }

        if ($type === static::META_ARRAY->value) {
            return json_encode($data);
        }

        if ($type === static::META_BOOLEAN->value) {
            return $data ? 'true' : 'false';
        }

        if ($type === static::META_NULL->value) {
            return static::META_NULL->value;
        }

        return $data;
    }

    public static function decode($data, string $type)
    {
        if ($type === static::META_MODEL->value) {
            if ( strpos( $data, '#' ) === false ) {
                return new $data();
            }
            list( $class, $id ) = explode( '#', $data );
            
            return static::resolveModelInstance( $class, $id );
        }
        
        if ($type === static::META_COLLECTION->value) {
            $data = json_decode($data, true);
            return new Collection($data);
        }

        if ( $type === static::META_OBJECT->value ) {
            return json_decode( $data );
        }

        if ($type === static::META_ARRAY->value) {
            return json_decode($data, true);
        }

        if ($type === static::META_BOOLEAN->value) {
            return filter_var($data, FILTER_VALIDATE_BOOLEAN);
        }

        if ($type === static::META_NULL->value) {
            return null;
        }

        if ($type === static::META_INTEGER->value) {
            return intval($data);
        }

        if ($type === static::META_DOUBLE->value) {
            return floatval($data);
        }

        return $data;
    }

	protected static function resolveModelInstance($model, $Key) {
		return (new $model())->findOrFail( $Key );
	}

    private static function isJson($string) {
        return (bool) preg_match('/^\s*(\{.*\}|\[.*\])\s*$/s', $string);
    }
}