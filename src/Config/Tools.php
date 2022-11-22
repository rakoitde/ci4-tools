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

use CodeIgniter\Config\BaseConfig;

class Tools extends BaseConfig
{
    /**
     * Environments
     *
     * @var array
     */
    public $environments = ['dev', 'test', 'prod'];

    /**
     * Current environment
     *
     * @var string
     */
    public $currentenvironment = 'dev';

    /**
     * Database group for development
     *
     * @var string
     */
    public $db_group_dev = 'dev_db';

    /**
     * Database group for testing
     *
     * @var string
     */
    public $db_group_test = 'test_dp';

    /**
     * Database group for produktive
     *
     * @var string
     */
    public $db_group_prod = 'prod_db';
}
