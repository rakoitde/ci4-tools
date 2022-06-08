<?php

namespace Rakoitde\Tools\Config;

use CodeIgniter\Config\BaseConfig;

class Tools extends BaseConfig
{

	/**
	 * Database group for development
	 *
	 * @var string
	 */
	public $db_group_dev = 'default';

    /**
     * Database group for testing
     *
     * @var string
     */
    public $db_group_test = 'kitrz_demo';

    /**
     * Database group for produktive
     *
     * @var string
     */
    public $db_group_prod = 'live';

}
