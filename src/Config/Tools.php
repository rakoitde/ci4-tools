<?php

namespace Rakoitde\Tools\Config;

use CodeIgniter\Config\BaseConfig;

class Tools extends BaseConfig
{

	/**
	 * Generatiors ini filename
	 *
	 * @var string
	 */
	public $db_group_dev = 'default';

	/**
	 * Components
	 *
	 * @var string
	 */
	public $db_group_prod = 'prod';

}
