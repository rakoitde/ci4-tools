<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
