# Slug plugin for CakePHP

[![Build Status](https://scrutinizer-ci.com/g/kicaj/slug/badges/build.png?b=master)](https://scrutinizer-ci.com/g/kicaj/slug/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/kicaj/slug/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/kicaj/slug/?branch=master)
[![LICENSE](https://img.shields.io/github/license/kicaj/slug.svg)](https://github.com/kicaj/slug/blob/master/LICENSE)
[![Releases](https://img.shields.io/github/release/kicaj/slug.svg)](https://github.com/kicaj/slug/releases)

## Requirements

It is developed for CakePHP 3.x.

## Installation

You can install plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require kicaj/slug
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
        'field' => 'name', // Field to create slug, default is title
        'finder' => 'some', // You can build own custom finder method, like findSome, default is built-in list
        'present' => true, // Rewrite slug, default is false, was added in 1.1.0
        'method' => 'someOther', // You can build own method for create string slug, now default is built-in Text::slug, was added in 1.2.0
    ],
]);
```
