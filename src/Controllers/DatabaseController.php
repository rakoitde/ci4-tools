<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Rakoitde\Tools\Controllers;

use App\Controllers\BaseController;

use Rakoitde\Tools\DatabaseTools;

class DatabaseController extends BaseController
{

    public function index()
    {

        $tools = new DatabaseTools();

        $tools->compare(["dms_document","dms_relation"]);

d($tools); 
 
    }

    public function __construct()
    {

    }

}
