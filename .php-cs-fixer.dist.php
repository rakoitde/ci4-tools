<?php

use CodeIgniter\CodingStandard\CodeIgniter4;
use Nexus\CsConfig\Factory;

#return Factory::create(new CodeIgniter4())->forProjects();


return Factory::create(new CodeIgniter4())->forLibrary(
    'CodeIgniter 4 Tools',
    'Ralf Kornberger',
    'rakoitde@gmail.com',
    2022,
);