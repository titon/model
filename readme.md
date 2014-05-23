# Model v0.3.2 [![Build Status](https://travis-ci.org/titon/model.png)](https://travis-ci.org/titon/model) #

The Titon model package provides an [active record](http://en.wikipedia.org/wiki/Active_record_pattern) style
approach to database CRUD functionality. Further provides data validation, field protection, and table relationship support.

```php
$user = User::find(1);
$user->username = 'foobar';
$user->save();
```

Outside of the active record structure, a handful of static methods can be used for basic
database functionality, like inserting, deleting, selecting, and updating.

```php
User::insert(['username' => 'foobar']);
User::select()->all();
User::deleteBy(1);
User::updateBy(1, ['username' => 'foobar']);
```

A full list of database features can be found under the [DB package](https://github.com/titon/db).

Alongside the DBAL is an extensible object relational mapper (ORM) that permits repositories (database tables) to
relate records to other repository records through foreign keys. Related data can also be saved automatically while saving parent records,
and can be pulled in automatically and easily through the query builder. The ORM is fully compatible with schemaless/NoSQL database drivers.

```php
$users->hasOne('Profile', 'App\Repository\Profile', 'profile_id');

$entity = $users->select()->with('Profile')->where('id', 1)->first();
```

### Features ###

* `Model` - Active record model
    * Relationships
    * Validation
    * Data filling
    * Data guarding
    * Accessors
    * Mutators

### Dependencies ###

* `DB`

### Requirements ###

* PHP 5.4.0

### Upcoming Features ###

* Polymorphic relations
* Refactored lazy/eager loading of relations