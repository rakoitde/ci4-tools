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

$routes->group(
    'tools',
    ['namespace' => 'Rakoitde\Tools\Controllers'],
    static function ($routes) {
        //$routes->get('/',       'AuthController::index', ['filter' => 'haspermissions:Auth.Module']);

        $routes->add('comparedb', 'DatabaseCompare::index');
        $routes->add('backupdb', 'DatabaseBackup::index');
        $routes->add('db/(:segment)/backup', 'DatabaseBackup::backup/$1');
    }
);
