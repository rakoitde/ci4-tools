<?php

namespace Rakoitde\Tools\Config;

use CodeIgniter\Config\BaseConfig;

class Tools extends BaseConfig
{

	/**
	 * Default namespace
	 *
	 * @var string
	 */
	public $defaultNamespace = 'Rakoitde/Test';

	/**
	 * Ignore namespaces
	 *
	 * @var array
	 */
	public $ignoreNamespaces = ['CodeIgniter','Config'];

	/**
	 * Generatiors ini filename
	 *
	 * @var string
	 */
	public $ini = 'Generators.ini';

	/**
	 * Components
	 *
	 * @var array
	 */
	public $components = ['Alert', 'Breadcrumb', 'Chart', 'Pagination', 'table'];

}
