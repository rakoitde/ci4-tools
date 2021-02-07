<?php

/**
 * This file is part of the CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rakoitde\Tools\Commands\Generators;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
#use CodeIgniter\CLI\GeneratorTrait;
use Rakoitde\Tools\Commands\Generators\GenTrait;

/**
 * Generates a skeleton command file.
 */
class LayoutGenerator extends BaseCommand
{
	#use GeneratorTrait;
	use GenTrait;

	/**
	 * The Command's Group
	 *
	 * @var string
	 */
	protected $group = 'Bootstrap';

	/**
	 * The Command's Name
	 *
	 * @var string
	 */
	protected $name = 'build:layout';

	/**
	 * The Command's Description
	 *
	 * @var string
	 */
	protected $description = 'Generates a new Layout.';

	/**
	 * The Command's Usage
	 *
	 * @var string
	 */
	protected $usage = 'build:layout <name> [options]';

	/**
	 * The Command's Arguments
	 *
	 * @var array
	 */
	protected $arguments = [
		'name' => 'The layout file name.',
	];

	/**
	 * The Command's Options
	 *
	 * @var array
	 */
	protected $options = [
		'--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".',
		'--suffix'    => 'Append the component title to the class name (e.g. User => UserCommand).',
		'--force'     => 'Force overwrite existing file.',
	];

	/**
	 * Actually execute a command.
	 *
	 * @param array $params
	 */
	public function run(array $params)
	{

		$this->component = 'Layout';
		$this->directory = 'Views';
		#$this->template  = 'layout.tpl.php';

		$this->readConfig();
		$this->namespace = $this->getNamespace();
		$this->template  = $this->getViews("layout", $this->template);
		$this->config->temp['lastQualifiedLayoutName'] = $this->qualifyViewName();
		$this->writeConfig();

		$this->execute($params);

	}

	/**
	 * Prepare options and do the necessary replacements.
	 *
	 * @param string $class
	 *
	 * @return string
	 */
	protected function prepare(string $class): string
	{
		$lastQualifiedLayoutName     = $this->config->temp['lastQualifiedLayoutName']     ?? "";
		$lastQualifiedBreadcrumbName = $this->config->temp['lastQualifiedBreadcrumbName'] ?? "";
		$lastQualifiedAlertName      = $this->config->temp['lastQualifiedAlertName']      ?? "";

		$search  = ['{lastQualifiedLayoutName}', '{lastQualifiedBreadcrumbName}', '{lastQualifiedAlertName}'];
		$replace = [$lastQualifiedLayoutName   , $lastQualifiedBreadcrumbName   , $lastQualifiedAlertName];
		$data    = [];

		return $this->parseViewTemplate(
			$class,
			$search,
			$replace,
			$data
		);
	}

}
