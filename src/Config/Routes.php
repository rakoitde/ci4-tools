<?php

helper('auth');

$routes->group(

	'tools', ['namespace' => 'Rakoitde\Tools\Controllers'], function ($routes) {
		#$routes->get('/',       'AuthController::index', ['filter' => 'haspermissions:Auth.Module']);

		$routes->add('comparedb', 'DatabaseCompare::index');
		$routes->add('backupdb', 'DatabaseBackup::index');
		$routes->add('db/(:segment)/backup', 'DatabaseBackup::backup/$1');
	}
);
