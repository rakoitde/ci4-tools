<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

helper('auth');

$routes->group('tools', ['namespace' => 'Rakoitde\Tools\Controllers'], static function ($routes) {
    $routes->get('database', 'DatabaseController::index');
    $routes->add('comparedb', 'DatabaseCompare::index');
    $routes->add('backupdb', 'DatabaseBackup::index');
    $routes->add('db/(:segment)/backup', 'DatabaseBackup::backup/$1');
});

$routes->group('api/tools', ['namespace' => 'Rakoitde\Tools\Controllers'], static function ($routes) {
    $routes->get('environment', 'ApiDatabaseController::environment');
    $routes->get('tables', 'ApiDatabaseController::tables');
    $routes->get('compare/(:any)', 'ApiDatabaseController::compare/$1');
});
