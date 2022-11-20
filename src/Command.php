<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Rakoitde\Tools;

use Rakoitde\Tools\Config\Tools;

/**
 * This class describes database tools.
 */
class Command
{
    /**
     * create, update, alter
     *
     * @var string
     */
    public $action;

    /**
     * db group name
     *
     * @var string
     */
    public $dbgroup;

    /**
     * table name
     *
     * @var string
     */
    public $table;

    /**
     * query to get the result
     */
    public $query;

    /**
     * the query result
     *
     * @var string
     */
    public $result;

    /**
     * table, view, field
     *
     * @var string
     */
    public $type;

    /**
     * created command
     *
     * @var string
     */
    public $command;
}
