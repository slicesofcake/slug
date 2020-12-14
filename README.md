# CakePHP plugin for slugs

[![Build Status](https://scrutinizer-ci.com/g/slicesofcake/slug/badges/build.png?b=master)](https://scrutinizer-ci.com/g/slicesofcake/slug/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/slicesofcake/slug/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/slicesofcake/slug/?branch=master)
[![LICENSE](https://img.shields.io/github/license/slicesofcake/slug.svg)](https://github.com/slicesofcake/slug/blob/master/LICENSE)
[![Releases](https://img.shields.io/github/release/slicesofcake/slug.svg)](https://github.com/slicesofcake/slug/releases)

Automatic creation of friendly links based on title or name.

## Requirements

It is developed for CakePHP 4.x.

## Installation

You can install plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require slicesofcake/slug
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
    'slug' => [ // Target field name of column to store slugs, default is slug
        'source' => 'name', // Source field name to create slug, default is title
        'replacement' => '_', // Default is -
        'finder' => 'some', // You can build own custom finder method, like findSome, default is built-in list
        'present' => true, // Rewrite slug, default is false, was added in 1.1.0
        'method' => 'someOther', // You can build own method for create string slug, now default is built-in Text::slug, was added in 1.2.0
    ],
]);
```
