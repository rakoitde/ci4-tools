# ci4-tools
 Codeigniter 4 i-doit module with i-doit api

## About

[i-doit](https://i-doit.com) is a software application for IT documentation and a CMDB (Configuration Management Database). i-doit is a Web application and [has an API](https://kb.i-doit.com/pages/viewpage.action?pageId=37355644) which is very useful to automate your infrastructure.

This CodeIgniter 4 module uses the i-doit API client library (https://github.com/bheisig/i-doit-api-client-php) to read and change configuration items from Codeigniter 4.

## Requirements

Meet these simple requirements before using the client:

-   A running instance of i-doit pro/open, version 1.14.1 or higher (older versions may work but are not supported)
-   i-doit API add-on, version 1.11 or higher (older versions may work but are not supported)
-   PHP, version 7.2 or higher (7.4 is recommended, unreleased 8.0 should work)
-   PHP modules `curl`, `date`, `json`, `openssl` and `zlib`

As a rule of thumb, always use the latest stable releases to benefit from new features, improvements and bug fixes.

## Installation

It is recommended to install this client via [Composer](https://getcomposer.org/). Change to your project's root directory and fetch the latest stable version:

~~~ {.bash}
composer require rakoitde/ci4-idoit-module
~~~

Instead of sticking to a specific/minimum version you may switch to the current development branch by using `@DEV`:

~~~ {.bash}
composer require "rakoitde/ci4-idoit-module=@DEV"
~~~

## Updates

Composer has the great advantage (besides many others) that you can simply update the API client library by running:

~~~ {.bash}
composer update
~~~

## Configuration

The API client library class requires a configuration:

~~~ {.php}
use bheisig\idoitapi\API;

$api = new API([
    API::URL => 'https://demo.i-doit.com/src/jsonrpc.php',
    API::PORT => 443,
    API::KEY => 'c1ia5q',
    API::USERNAME => 'admin',
    API::PASSWORD => 'admin',
    API::LANGUAGE => 'en',
    API::PROXY => [
        API::PROXY_ACTIVE => false,
        API::PROXY_TYPE => 'HTTP', // 'HTTP' or 'SOCKS5'
        API::PROXY_HOST => 'proxy.example.net',
        API::PROXY_PORT => 8080,
        API::PROXY_USERNAME => '',
        API::PROXY_PASSWORD => ''
    ],
    API::BYPASS_SECURE_CONNECTION => false
]);
~~~

-   `API::URL`: URL to i-doit's API, probably the base URL appended by `src/jsonrpc.php`
-   `API::PORT`: optional port on which the Web server listens; if not set port 80 will be used for HTTP and 443 for HTTPS
-   `API::KEY`: API key
-   `API::USERNAME` and `API::PASSWORD`: optional credentials if needed, otherwise `System API` user will be used
-   `API::LANGUAGE`: requests to and responses from i-doit will be translated to this language (`de` and `en` supported); this is optional; defaults to user's prefered language
-   `API::PROXY`: use a proxy between client and server
    -   `API::PROXY_ACTIVE`: if `true` proxy settings will be used
    -   `API::PROXY_TYPE`: use a HTTP (`API::PROXY_TYPE_HTTP`) or a SOCKS5 (`API::PROXY_TYPE_SOCKS5`) proxy
    -   `API::PROXY_HOST`: FQDN or IP address to proxy
    -   `API::PROXY_PORT`: port on which the proxy server listens
    -   `API::PROXY_USERNAME` and `API::PROXY_PASSWORD`: optional credentials used to authenticate against the proxy
-   `API::BYPASS_SECURE_CONNECTION`: Set to `true` to disable security-related cURL options; defaults to `false`; do not set this in production! 

## Helper

### Auth Helper
This helper is loaded using the following code:

~~~ {.php}
`helper('auth');`
~~~

#### Available Functions

The following functions are available:

logged_in()
-   Returns: true or false
-   Returntype: boolean

user()
-   Returns: The UserEntity or null if user is not login
-   Returntype: \Rakoitde\Idoit\Entities\UserEntity|null

user_id()
-   Returns: The UserID or null if is user not login
-   Returntype: int|null

username()
-   Returns: The Username or null if user is not login
-   Returntype: string|null

groups()
-   Returns: The Groups
-   Returntype: array

in_groups()
-   Parameters: mixed $groups
-   Returns: The Groups
-   Returntype: array

## Examples

A basic "Hello, World!" example is to fetch some basic information about your i-doit instance:

~~~ {.php}
class YourController extends BaseController
{
    public function index()
    {

        $idoitModel = new \Rakoitde\Idoit\Models\IdoitModel();
        $version = $idoitModel->getVersion();
        d($version);
            
    }
}
~~~

It is simple like that. For more examples take a look at the next sub sections.

### Examples

### Fetch next free IP address from subnet

~~~ {.php}
use bheisig\idoitapi\API;
use bheisig\idoitapi\Subnet;

$api = new API([/* … */]);

$subnet = new Subnet($api);
// Load subnet object by its identifier:
$nextIP = $subnet->load(123)->next();

echo 'Next IP address: ' . $nextIP . PHP_EOL;
~~~

### Upload files

This API client library is able to upload a file, create a new "File" object an assigned it to an existing object identified by its ID:

~~~ {.php}
use bheisig\idoitapi\API;
use bheisig\idoitapi\File;

$api = new API([/* … */]);

$file = new File($api);

// Assign one file to object with identifier 100:
$file->add(100, '/path/to/file', 'my file');

// Assign many files to this object:
$file->batchAdd(
    100,
    [
        'file1.txt' => 'File 1',
        'file2.txt' => 'File 2',
        'file3.txt' => 'File 3'
    ]
);
~~~

### Upload images to a gallery

Each object may have an image gallery provided by assigned category "images". This is the way to upload image files and assign them to an existing object:

~~~ {.php}
use bheisig\idoitapi\API;
use bheisig\idoitapi\Image;

$api = new API([/* … */]);

$image = new Image($api);

// Assign one image with a caption to object's gallery with identifier 100:
$image->add(100, '/path/to/flowers.jpg', 'nice picture of flowers');

// Assign many images to this object:
$file->batchAdd(
    100,
    [
        'file1.jpg' => 'JPEG file',
        'file2.png' => 'PNG file',
        'file3.bmp' => 'BMP file',
        'file3.gif' => 'Animated GIF file'
    ]
);
~~~

### Self-defined request

Sometimes it is better to define a request on your own instead of using pre-defined methods provided by this API client library. Here is the way to perform a self-defined request:

~~~ {.php}
use bheisig\idoitapi\API;

$api = new API([/* … */]);

$result = $api->request('idoit.version');

var_dump($result);
~~~

`request()` takes the method and optional parameters.

### Self-defined batch request

Similar to a simple requests you may perform a batch requests with many sub-requests as you need:

~~~ {.php}
use bheisig\idoitapi\API;

$api = new API([/* … */]);

$result = $api->batchRequest([
    [
        'method' => 'idoit.version'
    ],
    [
       'method' => 'cmdb.object.read',
       'params' => ['id' => 1]
    ]
]);

var_dump($result);
~~~

### Read information about your CMDB design

Fetch information about object types, object types per group, categories assigned to object types, and attributes available in categories:

~~~ {.php}
use bheisig\idoitapi\API;
use bheisig\idoitapi\CMDBObjectTypes;
use bheisig\idoitapi\CMDBObjectTypeGroups;
use bheisig\idoitapi\CMDBObjectTypeCategories;
use bheisig\idoitapi\CMDBCategoryInfo;

$api = new API([/* … */]);

// Object types:
$objectTypes = new CMDBObjectTypes($api);
$allObjectTypes = $objectTypes->read();
var_dump($allObjectTypes);
$server = $objectTypes->readOne('C__OBJTYPE__SERVER');
var_dump($server);
$someObjectTypes = $objectTypes->batchRead('C__OBJTYPE__SERVER', 'C__OBJTYPE__CLIENT');
var_dump($someObjectTypes);
$client = $objectTypes->readByTitle('LC__CMDB__OBJTYPE__CLIENT');
var_dump($client);

// Object types per group:
$objectTypesPerGroup = new CMDBObjectTypeGroups($api);
$objectTypes = $objectTypesPerGroup->read();
var_dump($objectTypes);

// Categories assigned to object types:
$assignedCategory = new CMDBObjectTypeCategories($api);
$serverCategories = $assignedCategory->readByConst('C__OBJTYPE__SERVER');
var_dump($serverCategories);
// Read by identifiers is also possible. And there are methods for batch requests.

// Attributes available in categories:
$categoryInfo = new CMDBCategoryInfo($api);
$modelCategory = $categoryInfo->read('C__CATG__MODEL');
var_dump($modelCategory);
$categories = $categoryInfo->batchRead([
    'C__CATG__MODEL',
    'C__CATG__FORMFACTOR',
    'C__CATS__PERSON_MASTER'
]);
var_dump($categories);
~~~

### Read information about i-doit itself

~~~ {.php}
use bheisig\idoitapi\API;
use bheisig\idoitapi\Idoit;

$api = new API([/* … */]);
$idoit = new Idoit($api);

$version = $idoit->readVersion();
$constants = $idoit->readConstants();
$addOns = $idoit->getAddOns();
$license = $idoit->getLicense();

var_dump($version, $constants, $addOns, $license);
~~~

### Re-connect to server

Sometimes you need a fresh connection. You may explicitly disconnect from the i-doit server and re-connect to it:

~~~ {.php}
use bheisig\idoitapi\API;

$api = new API([/* … */]);

// Do your stuff…
$api->disconnect();
$api->isConnected(); // Returns false
$api->connect();
$api->isConnected(); // Returns true
~~~

### Debug API calls

For debugging purposes it is great to fetch some details about your API calls. This script uses some useful methods:

~~~ {.php}
#!/usr/bin/env php
<?php

use bheisig\idoitapi\API;
use bheisig\idoitapi\Idoit;

$start = time();

require_once 'vendor/autoload.php';

$api = new API([/* … */]);

// @todo Insert your code here, for example:
$request = new Idoit($api);
$request->readVersion();

fwrite(STDERR, 'Last request:' . PHP_EOL);
fwrite(STDERR, '=============' . PHP_EOL);
fwrite(STDERR, $api->getLastRequestHeaders() . PHP_EOL);
fwrite(STDERR, json_encode($api->getLastRequestContent(), JSON_PRETTY_PRINT) . PHP_EOL);
fwrite(STDERR, PHP_EOL);
fwrite(STDERR, '--------------------------------------------------------------------------------' . PHP_EOL);
fwrite(STDERR, 'Last response:' . PHP_EOL);
fwrite(STDERR, '==============' . PHP_EOL);
fwrite(STDERR, $api->getLastResponseHeaders() . PHP_EOL);
fwrite(STDERR, json_encode($api->getLastResponse(), JSON_PRETTY_PRINT) . PHP_EOL);
fwrite(STDERR, PHP_EOL);
fwrite(STDERR, '--------------------------------------------------------------------------------' . PHP_EOL);
fwrite(STDERR, 'Last connection:' . PHP_EOL);
fwrite(STDERR, '================' . PHP_EOL);
$info = $api->getLastInfo();
unset($info['request_header']);
foreach ($info as $key => $value) {
    if (is_array($value)) {
        $value = '…';
    }
    fwrite(STDERR, $key . ': ' . $value . PHP_EOL);
}
fwrite(STDERR, '--------------------------------------------------------------------------------' . PHP_EOL);
fwrite(STDERR, 'Amount of requests: ' . $api->countRequests() . PHP_EOL);
$memoryUsage = memory_get_peak_usage(true);
fwrite(STDERR, sprintf('Memory usage: %s bytes', $memoryUsage) . PHP_EOL);
$duration = time() - $start;
fwrite(STDERR, sprintf('Duration: %s seconds', $duration) . PHP_EOL);
~~~

## Contribute

Please, report any issues to [our issue tracker](https://github.com/bheisig/i-doit-api-client-php/issues). Pull requests are very welcomed. If you like to get involved see file [`CONTRIBUTING.md`](CONTRIBUTING.md) for details.

## Projects using this API client library

-   [i-doit CLI Tool](https://github.com/bheisig/i-doit-cli) – "Access your CMDB on the command line interface"
-   [i-doit Check_MK 2 add-on](https://www.i-doit.com/en/i-doit/add-ons/check-mk-add-on-2/) – "Share information between i-doit and Check_MK"

Send pull requests to add yours.

## Copyright & License

Copyright (C) 2016-2020 [Benjamin Heisig](https://benjamin.heisig.name/)

Licensed under the [GNU Affero GPL version 3 or later (AGPLv3+)](https://gnu.org/licenses/agpl.html). This is free software: you are free to change and redistribute it. There is NO WARRANTY, to the extent permitted by law.