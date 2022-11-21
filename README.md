# CodeIgniter Tools
 Codeigniter 4 Tools module

## About

This CodeIgniter Tools expand the existing functionalities of the ci4 framework with many functions that were missing for me. This includes commands for initializing a fresh appstarter installation or extensions for the make commands

## Requirements

Meet these simple requirements before using the client:

-   Always use the latest stable CodeIgniter 4 release

## Installation

It is recommended to install this client via [Composer](https://getcomposer.org/). Change to your project's root directory and fetch the latest stable version:

~~~ {.bash}
composer require rakoitde/ci4-tools
~~~

Instead of sticking to a specific/minimum version you may switch to the current development branch by using `@DEV`:

~~~ {.bash}
composer require "rakoitde/ci4-tools=@DEV"
~~~

## Updates

Composer has the great advantage (besides many others) that you can simply update the API client library by running:

~~~ {.bash}
composer update
~~~

## Documentation

[Database Tools - EntityRelationTrait](ENTITYRELATIONTRAIT.md)

## Tools

### Commands

#### init

~~~ {.bash}
php spark init -all
~~~



### Helper

#### Auth Helper

## Contribute

### Coding Standard

~~~ {.bash}
./vendor/bin/php-cs-fixer fix --verbose
~~~

I'm a hobby programmer who sometimes has more or less time to work on his programming projects. Furthermore, I am not familiar with all github possibilities like pull requests. So yes, contribute, but be patient with me. Many Thanks.

## Copyright & License

