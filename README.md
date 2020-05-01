# DTO Mapper

## About

This class helps to map any class or associative array tree into DTO class tree. 

It uses reflection to create DTOs but calls getters or reads properties to get values
from source tree and only properties found in DTO will be fetched from source object.
This makes mapper work fine e.g. with doctrine lazy loads. 

For object `object` if key property `property` is required, following sources will be 
checked:
* `object->propery`
* `object[property]`
* `object->getPropery()`
* `object->isPropery()`
* `object->hasPropery()`
* `object->propery()`
* `object->__call('property')`

All method calls are case-insensitive.

DTOs must have all their properties typed and all array properties should have phpDoc
specifying item types. 

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Run

```
composer require onmoon/dto-mapper
```

## Usage

```php
public function showPetById(int $petId) : ShowPetByIdResponseDto
{
    $pet   = $this->pets->getById($petId);

    $mapper = new OnMoon\DtoMapper\DtoMapper();
    return $mapper->map($pet, ShowPetByIdResponseDto::class);
}
```

## DateTime handling

`DateTime` class does not support creation with reflection. If DTO property is
subclass of `DateTimeInterface` then this property is set to new `\Safe\DateTime`
instance. Source values for `DateTime` can be both `DateTimeInterface` and `string`.

## Rewriting source properties

By default, mapper searches for the same property in source as in DTO.
If you want to read value for some property from another source property, you can
pass a callable object as third argument. This callable should always return mapped 
property name.

```php
$mapper->map($pet, ShowPetByIdResponseDto::class, function ($propertyName, $context) { 
    $fullName = implode('->', [...$context, $propertyName]);
    if ($fullName === 'subObject->s2->property1') {
       return 'realProperty1';
    }
    return $propertyName;
});
```
