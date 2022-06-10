<?php

namespace Rakoitde\Tools\Config;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function dbtools()
    {
        return new \Rakoitde\Tools\DatabaseTools();
    }

    // ...
}