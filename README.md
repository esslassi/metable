# Add Meta Data To Eloquent Models
 Laravel MetaData for Eloquent Models

[![Laravel](https://img.shields.io/badge/Laravel-~9.0-green.svg?style=flat-square)](http://laravel.com)
[![Source](http://img.shields.io/badge/source-esslassi/metable-blue.svg?style=flat-square)](https://github.com/esslassi/metable/)
[![License](http://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://tldrlegal.com/license/mit-license)

## Installation

#### Composer

Laravel can be installed on laravel `9.x` or higher.

Run:

```
composer require esslassi/metable
```

Register the package's service provider in config/app.php:

```php
'providers' => [
    ...
    Esslassi\Metable\MetableServiceProvider::class,
    ...
];
```

You should publish the migration and the config/metable.php config file with:

```php
php artisan vendor:publish --provider="Esslassi\Metable\MetableServiceProvider::class"
```

You can customize the default meta table (default: `meta`) in the config/metable.php:

```php
return [

    /*
	 *
     * You can customize default table name by relacing 'meta' to your own
     *
     */

    'tables' => [

        // default table for all models

        'default' => 'meta'
        
    ]
];
```

Execute the migration command to create the meta table after the config and migration have been published and configured:

```php
php artisan migrate
```

## Configuration

#### Model Setup

Add the Metable trait to your models you need to be metable:

```php
use Esslassi\Metable\Metable;

class User extends Eloquent
{
    use Metable;
    ...
}
```

#### Default Model Attribute values

Additionally, you can specify default values by assigning an array called `$defaultMetaValues` to the model. This configuration has two side effects:

1. If a meta attribute does not exist, the default value will be returned instead of null.
2. If you set a meta attribute to its default value, the row in the meta table will be removed, causing the default value to be returned as described in rule 1.

This is being the desired and expected functionality for most projects, but be aware that you may need to reimplement default functionality with your own custom accessors and mutators if this functionality does not fit your needs.

This functionality is most suited for meta entries that note exceptions to rules. For example: employees sick out of office (default value: in office), nodes taken down for maintenance (default value: node up), etc. This means the table doesn't need to store data on every entry which is in the expected state, only those rows in the exceptional state, and allows the rows to have a default state upon creation without needing to add code to write it.

```
   public $defaultMetaValues = [
      'is_admin' => false,
   ];
```

## Metable Proccess

#### Setting Content Meta

To set a meta value on an existing attribute or create new data:

1. Fluent way: You can set meta values seamlessly just like you do with regular Eloquent models.
2. Metable checks if the attribute belongs to the model; if it doesn't, it will access the meta model to append or set a new meta value.

```php
$user = new User;
$user->name = 'Esslassi Mohammed'; // model attribute
$user->country = 'Morocco'; // meta data attribute
$user->save(); // save model
```

You can use `setMeta` to set or add a meta value

```php
$user = new User;
$user->name = 'Esslassi Mohammed';
$user->setMeta('country', 'Morocco');
$user->save();
```

Setting multiple metas:

```php
$user = new User;
$user->name = 'Esslassi Mohammed';
$user->setMeta([
    'country' => 'Morocco',
    'city' => 'Fez'
    ...
]);
$user->save();
```

Setting multiple metas with model columns:

```php
$user = new User;
$user->setAttributes([
    'name' => 'Esslassi Mohammed',
    'country' => 'Morocco',
    'city' => 'Fez'
    ...
]);
$user->save();
```

Using standard `create` method to create model:

> If an attribute doesn't exists on model attributes it will added as meta values.

```php
User::create([
    'name' => 'Esslassi Mohammed',
    'country' => 'Morocco',
    'city' => 'Fez'
    ...
]);
```

#### Removing Content Meta

As usual if you want to remove a meta value you can use `unset` and the save the model:

```php
$user = User::find(1);
$user->name // model attribute
unset($user->country) // remove meta on save
$user->save();
```
Using `removeMeta`:

```php
$user = User::find(1);
$user->removeMeta('country');
$user->save();
```

Removing multiple metas:

```php
$user = User::find(1);

$user->removeMeta(['country', 'city']);
OR
$user->removeMeta('country', 'city');

$user->save();
```

Deleting all meta at once:
```php
$user = User::find(1);
$user->deleteAllMeta();
```

> **Note:** This function will delete all metas from database directly with no rollback available.

#### Checking for Metas

To see if a piece of content has a meta:

```php
...
if (isset($user->country)) {
    ...
}
...
```

You can check if meta exists by `hasMeta` method:

```php
...
if ($user->hasMeta('country')){

}
...
```

You may also check if model has multiple metas:

```php
//By providing an array
$user->hasMeta(['country', 'city']);
// By pipe spliter
$user->hasMeta('country|city');
// By comma spliter
$user->hasMeta('country,city');
```

> **Note:** It will return true only if all the metas exist.

If you provide a `true` in second arg it will return true even if meta marked for deletion:

```php
$user = User::find(1);
$user->removeMeta('country');

$user->hasMeta('country', true); // Return true

$user->hasMeta('country'); // Return false
...
```

#### Retrieving Meta


> **Fluent way**, You can access meta data as if it is a property on your model.
> Just like you do on your regular eloquent models.

```php
$user = User::find(1);
$user->name; // Return Eloquent value
$user->country; // Return meta value
```

You can retrieve a meta value by using the `getMeta` method:

```php
$user = $user->getMeta('country');
```

Or specify a default value, if not exists:

```php
$user = $user->getMeta('country', 'Morocco');
```

> **Note:** default values set in the `$defaultMetaValues` property take precedence over any default value passed to this method.

You can also retrieve multiple meta values at once and receive them as an collection.

```php
// By comma
$user = $user->getMeta('country,city');
// By pipe
$user = $user->getMeta('country|city');
// By an array
$user = $user->getMeta(['country', 'city']);
```

#### Disable Fluent Access

In some cases you want to disable fluent access or setting:

```php
protected $disableFluentMeta = true;
```

By setting that property, you will no longer setting or accessing to meta value by fluent way:

```php
$user = User::find(1);
$user->country = 'Morocco';// This will not set meta, this action will call setAttribute() of model class.
$user->country;// Here will not retrieve meta
unset($user->country);// No action will take
isset($user->country);// Will not check if meta exists
```

So the only way to set a meta is calling `setMeta` or `addMeta` methods

#### Retrieving All Metas

To fetch all metas associated with a piece of content, use the `getMeta` without any params

```php
$user = User::find(1);
$metas = $user->getAllMeta();
```

## Meta Clauses

#### Where Meta Clauses

You can use the meta query builder's `whereMeta` method to add `where` clauses to the meta query. The most basic call to the `whereMeta` method requires three arguments: the name of the column, the operator (which can be any of the database's supported operators), and the value to compare against the column's value.

For instance, the following query retrieves users whose country meta key value is 'Morocco':

```php
$users = User::whereMeta('country', '=', 'Morocco')
    ->get();
```

For convenience, if you want to verify that a column equals a given value, you can pass the value as the second argument to the `whereMeta` method. The package will assume you want to use the `=` operator:

```php
$users = User::whereMeta('country', 'Morocco')
    ->get();
```
#### Or Where Meta Clauses

When chaining calls to the meta query builder's `whereMeta` method, the clauses will be joined using the `AND` operator. However, you can use the `orWhereMeta` method to join a clause to the meta query using the `OR` operator. The `orWhereMeta` method accepts the same arguments as the `whereMeta` method:

```php
$users = User::whereMeta('country', 'Morocco')
    orWhereMeta('continent', 'Africa')
    ->get();
```

If you need to group an "or" condition within parentheses, this package doesn't support this option to do that just use Laravel where grouping:

```php
$users = User::whereMeta('country', 'Morocco')
    ->orWhere(function (Builder $query) {
        $query->whereMeta('continent', 'Africa')
            ->whereMeta('competition', 'CAN');
    })
    ->get();
```

#### Where Meta Not Clauses

The `whereMetaNot` and `orWhereMetaNot` methods can be used to negate specific query constraints. For example, the following query excludes users who live in Morocco:

```php
$users = User::whereMetaNot('country', 'Morocco')
    ->get();
```

#### Where Meta Not Clauses

The `whereMetaIn` and `orWhereMetaNotIn` methods testing if a meta in an array given:

```php
$users = User::whereMetaIn('country', ['Morocco', 'Algeria', 'Egypt', 'Tunisia'])
    ->get();
```

#### Additional Where Clauses

##### whereMetaNull / orWhereMetaNull

The whereMetaNull method verifies that a meta's value is null:

```php
$users = User::whereMetaNull('country')
    ->get();
```

##### whereMetaNotNull / orWhereMetaNotNull

The whereMetaNotNull method verifies that a meta's value is not null:

```php
$users = User::whereMetaNotNull('country')
    ->get();
```

##### whereMetaHas / orWhereMetaHas

The whereMetaHas method verifies that a meta key exists:

```php
$users = User::whereMetaNotNull('country')
    ->get();
```

##### whereMetaDoesntHave / orWhereMetaDoesntHave

The whereMetaDoesntHave method verifies that a meta key not exists:

```php
$users = User::whereMetaDoesntHave('country')
    ->get();
```

##### whereInMetaArray / whereNotInMetaArray (MySQL Only)

The whereInMetaArray method verifies if given value is in meta value. The following example retrieves users whose 'Morocco' in meta key value:

```php
$users = User::whereInMetaArray('countries', 'Morocco')
    ->get();
```

> **Note:** This method is equals to Laravel's `whereJsonContains` but since value column's type is long text we couldn't work with it so we decided to create this method to help us with this problem.

## Defining Relationships

Actually metable has one relationship so our trip in this section will be short.

### One to One

Imagin that we have in our app three roles `manager`, `driver`, `supervisor` but the only role must have a car is `driver` and you can't set forign key `user_id` to cars table because a car can be driven by one or more drivers:


```
users
    id - integer
    name - string
    role - string
 
cars
    id - integer
    model - string
    marque - string
```

So we decided to create an metable relationship calls MetaOne with initialization method `hasMetaOne`:

```php
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Esslassi\Metable\Relations\MetaOne;
 
class User extends Model
{
    /**
     * Get the car.
     */
    public function car(): MetaOne
    {
        return $this->hasMetaOne(Car::class, 'car_id');
    }
}
```

The first argument passed to the hasMetaOne method is the name of the final model we wish to access, while the second argument is the name of the meta key.

```php
$user = User::find(1);
$car = $user->car;
```

#### Key Conventions

Basically metable automatically recognize the local key of the model but the meta key name is required. So no need to identify the local key. If you would like to customize the local key of the relationship, you may pass it as the third argument to the hasMetaOne method.

```php
class User extends Model
{
    /**
     * Get the car.
     */
    public function car(): MetaOne
    {
        return $this->hasMetaOne(
            Car::class,
            'car_id', // Meta key of the users meta...
            'id' // Local key on the users table...
        );
    }
}
```