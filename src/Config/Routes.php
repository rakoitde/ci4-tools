<?php

helper('auth');

$routes->group(
	
    'tools', ['namespace' => 'Rakoitde\Tools\Controllers'], function ($routes) {
    
        #$routes->get('/',       'AuthController::index', ['filter' => 'haspermissions:Auth.Module']);

        $routes->add('comparedb',         'CompareDatabases::index');


    }
);

