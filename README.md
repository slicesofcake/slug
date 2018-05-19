# Slug plugin for CakePHP

**NOTE:** It's still in development mode, do not use in production yet!

## Requirements

It is developed for CakePHP 3.x.

## Installation

You can install plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require kicaj/slug dev-master
```

Load the Behavior
---------------------

Load the Behavior in your src/Model/Table/YourTable.php (or if You have AppTable.php). 
```
public function initialize(array $config)
{
    parent::initialize($config);

    $this->addBehavior('Slug.Slug');
}
```

You can configuration to customize the Slug plugin:
```
$this->addBehavior('Slug.Slug', [
    'slug' => [ // Name of column to store slugs, default is slug
        'replacement' => '_', // Default is -
        'field' => 'name' // Field to create slug, default is title
        'finder' => 'some' // You can build findSome, default is list
    ],
]);
```
