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

use Config\Services;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * GeneratorTrait contains a collection of methods
 * to build the commands that generates a file.
 */
trait GenTrait
{

	/**
	 * Execute the command.
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	protected function getNamespaces()
	{
		$autoload = new \Config\Autoload();

		$namespaces = [];
		foreach ($autoload->psr4 as $key => $val) {
			if (!in_array($key, $this->config->ignoreNamespaces)) {
				$namespaces[] = $key;
			}
		}
		return $namespaces;
	}

	/**
	 * Execute the command.
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	protected function getComponent()
	{

		$component = $this->getOption('component');
		$components = $this->config->components;

		if (!$component || !in_array($component, $components)) {

			for ($i=0; $i < count($components); $i++) { 
				CLI::write("[".($i+1)."] ".$components[$i], 'yellow');
			}

			// @codeCoverageIgnoreStart
			$id = CLI::prompt("Component auswählen", array_merge([0], range(1,count($components))), 'required');
			CLI::newLine();

			if (!$id) { $id=1; };

			// @codeCoverageIgnoreEnd

			$component = $components[$id-1];

		}

		return $component;
	
	}

	/**
	 * Execute the command.
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	protected function getNamespace()
	{

		$namespace = $this->getOption('namespace');
		$namespaces = $this->getNamespaces();

		if (!$namespace || !in_array($namespace, $namespaces)) {

			$lastNamespace    = $this->config->temp['lastNamespace']  ?? "";
			$defaultNamespace = $this->config->defaultNamespace ?? "";

			$default = "App";
			if ($lastNamespace!=="") {
				$default = $lastNamespace;
			} elseif ($defaultNamespace!=="") {
				$default = $defaultNamespace;
			}

			CLI::write("[0] Default: ".$default, 'yellow');
			for ($i=0; $i < count($namespaces); $i++) { 
				$d = ($namespaces[$i]==$default ? "* " : "");
				CLI::write("[".($i+1)."] ".$d.$namespaces[$i], 'yellow');
			}

			// @codeCoverageIgnoreStart
			$id = CLI::prompt("Namespace auswählen", array_merge([0], range(1,count($namespaces))), 'required');
			CLI::newLine();

			$this->config->temp['lastNamespace'] = $default;
			$this->writeConfig();
			if (!$id) { return $default; };

			// @codeCoverageIgnoreEnd

			$namespace = $namespaces[$id-1];
			$this->config->temp['lastNamespace'] = $namespace;
			$this->writeConfig();
		}

		return $namespace;
	
	}

	/**
	 * Get View.
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	protected function getViews($prefix, $default)
	{

		$default = str_replace(".tpl.php", "", $default);
		$config_default = "default".ucfirst($prefix);
		$config_last = "last".ucfirst($prefix);

		$views=[];
		foreach (glob(dirname(__FILE__)."/Views/".strtolower($prefix)."*.tpl.php") as $filename) {
			$views[] = basename($filename, ".tpl.php" );
		}


		#$config = $this->readConfig(dirname(__FILE__)."/".$this->ini);

		if (isset($this->config->temp[$config_last]) && $this->config->temp[$config_last]!=="") {
			$defaultview = $this->config->temp[$config_last];
		} elseif (isset($this->config->temp[$config_default]) && $this->config->temp[$config_default]!=="") {
			$defaultview = $this->config->temp[$config_default];
		} else {
			$defaultview = $default;
		}

		CLI::write("[0] Default: ".$defaultview, 'yellow');

		for ($i=0; $i < count($views); $i++) { 
			$d = ($views[$i]==$defaultview ? "* " : "");
			CLI::write("[".($i+1)."] ".$d.basename($views[$i], ".tpl.php" ), 'yellow');
		}

		// @codeCoverageIgnoreStart
		$id = CLI::prompt("View auswählen", array_merge([0], range(1,count($views))), 'required');
		CLI::newLine();

		$this->config->temp[$config_last] = $defaultview;

		CLI::write("View: ".$this->config->temp[$config_last].".tpl.php", 'red');
		$this->writeConfig();

		if (!$id) { return $defaultview.".tpl.php"; };
		// @codeCoverageIgnoreEnd

		$this->config->temp[$config_last] = $views[$id-1];
		CLI::write("View: ".$this->config->temp[$config_last].".tpl.php", 'green');
		$this->writeConfig();

		return $this->config->temp[$config_last].".tpl.php";

	}



	function writeConfig() {
		$filename = dirname(__FILE__)."/".$this->config->ini;
	    $fh = fopen($filename, "w");
	    if (!is_resource($fh)) {
	        return false;
	    }
	    ksort($this->config->temp);
	    foreach ($this->config->temp as $key => $value) {
	        fwrite($fh, sprintf("%s = %s\n", $key, $value));
	    }
	    fclose($fh);

	    return true;
	}

	function readConfig() {
		$this->config = config("Tools");
		$filename = dirname(__FILE__)."/".$this->config->ini;
		CLI::write("Read Config File: ".$filename , 'yellow');
		if (file_exists($filename)) {
	    	$this->config->temp = parse_ini_file($filename, false, INI_SCANNER_NORMAL);
		} else {
			$this->config->temp = [];
		} 
	}

	/**
	 * Performs pseudo-variables contained within view file.
	 *
	 * @param string $class
	 * @param array  $search
	 * @param array  $replace
	 * @param array  $data
	 *
	 * @return string
	 */
	protected function parseViewTemplate(string $class, array $search = [], array $replace = [], array $data = []): string
	{
		// Retrieves the namespace part from the fully qualified class name.
		$namespace = trim(implode('\\', array_slice(explode('\\', $class), 0, -1)), '\\');



		array_push($search, '<@php', '{namespace}', '{class}');
		array_push($replace, '<?php', $namespace, str_replace($namespace . '\\', '', $class));

		CLI::write("File: ".dirname(__FILE__), 'yellow');
		CLI::write("Template: ".$this->template);
		CLI::write("Namespace: ".$namespace);


		$parser = new \CodeIgniter\View\Parser(config("View"));

		$data = [
		        'namespace'   => 'My Blog Title',
		        'blog_heading' => 'My Blog Heading'
		];

		$namespace = trim(str_replace('/', '\\', $this->getOption('namespace') ?? APP_NAMESPACE), '\\');

		// Check if the namespace is actually defined and we are not just typing gibberish.
		$base = Services::autoloader()->getNamespace($namespace);
		print_r($base);
		$base = realpath($base[0]) ?: $base[0];
		print_r($base);

		#$filecontent =  $parser->setData($data)->render(dirname(__FILE__)."/Views/".$this->template);
		$templatename = dirname(__FILE__)."/Views/".$this->template;
		CLI::write("Templatename: ".$templatename);
		$filecontent = file_get_contents($templatename);

		return str_replace($search, $replace, $filecontent);

	}

	/**
	 * Gets a single command-line option. Returns TRUE if the option exists,
	 * but doesn't have a value, and is simply acting as a flag.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	protected function getOption(string $name)
	{
		if (! array_key_exists($name, $this->params))
		{
			return CLI::getOption($name);
		}

		return is_null($this->params[$name]) ? true : $this->params[$name];
	}

	/**
	 * Gets a single command-line option. Returns TRUE if the option exists,
	 * but doesn't have a value, and is simply acting as a flag.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	protected function getOptions()
	{
		return CLI::getOptions();
	}

/**
	 * The params array for easy access by other methods.
	 *
	 * @internal
	 *
	 * @var array
	 */
	private $params = [];

	/**
	 * Execute the command.
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	protected function execute(array $params): void
	{
		$this->params = $params;

		// Get the fully qualified class name from the input.
		$name = $this->qualifyViewName();

		// Get the file path from class name.
		$path = $this->buildPath($name);
print_r("Path: ".$path);
		// Check if path is empty.
		if (empty($path))
		{
			return;
		}

		$isFile = is_file($path);

		// Overwriting files unknowingly is a serious annoyance, So we'll check if
		// we are duplicating things, If 'force' option is not supplied, we bail.
		if (! $this->getOption('force') && $isFile)
		{
			CLI::error(lang('CLI.generator.fileExist', [clean_path($path)]), 'light_gray', 'red');
			CLI::newLine();

			return;
		}

		// Check if the directory to save the file is existing.
		$dir = dirname($path);

		if (! is_dir($dir))
		{
			mkdir($dir, 0755, true);
		}

		helper('filesystem');

		// Build the class based on the details we have, We'll be getting our file
		// contents from the template, and then we'll do the necessary replacements.
		if (! write_file($path, $this->buildContent($name)))
		{
			// @codeCoverageIgnoreStart
			CLI::error(lang('CLI.generator.fileError', [clean_path($path)]), 'light_gray', 'red');
			CLI::newLine();

			return;
			// @codeCoverageIgnoreEnd
		}

		if ($this->getOption('force') && $isFile)
		{
			CLI::write(lang('CLI.generator.fileOverwrite', [clean_path($path)]), 'yellow');
			CLI::newLine();

			return;
		}

		CLI::write(lang('CLI.generator.fileCreate', [clean_path($path)]), 'green');
		CLI::newLine();
	}

	/**
	 * Parses the class name and checks if it is already qualified.
	 *
	 * @return string
	 */
	protected function qualifyViewName(): string
	{
		// Gets the name from input.
		$name = $this->params[0] ?? CLI::getSegment(2);

		if (is_null($name) && $this->hasClassName)
		{
			// @codeCoverageIgnoreStart
			$name = CLI::prompt('Name:', null, 'required');
			CLI::newLine();
			// @codeCoverageIgnoreEnd
		}

		helper('inflector');

		$component = strtolower(singular($this->component));
		$name     = strtolower($name);
		$name     = strpos($name, $component) !== false ? str_replace($component, ucfirst($component), $name) : $name;

		if ($this->getOption('suffix') && ! strripos($name, $component))
		{
			$name .= ucfirst($component);
		}

		// Trims input, normalize separators, and ensure that all paths are in Pascalcase.
		$name = ltrim(implode('\\', array_map('pascalize', explode('\\', str_replace('/', '\\', trim($name))))), '\\/');

		// Gets the namespace from input.
		$namespace = trim(str_replace('/', '\\', $this->namespace), '\\');

		if (strncmp($name, $namespace, strlen($namespace)) === 0)
		{
			return $name; // @codeCoverageIgnore
		}

		return $namespace . '\\' . $this->directory . '\\' . str_replace('/', '\\', $name);
	}
	/**
	 * Parses the class name and checks if it is already qualified.
	 *
	 * @return string
	 */
	protected function qualifyClassName(): string
	{
		// Gets the name from input.
		$class = $this->params[0] ?? CLI::getSegment(2);

		if (is_null($class) && $this->hasClassName)
		{
			// @codeCoverageIgnoreStart
			$class = CLI::prompt('Name:', null, 'required');
			CLI::newLine();
			// @codeCoverageIgnoreEnd
		}

		helper('inflector');

		$component = strtolower(singular($this->component));
		$class     = strtolower($class);
		$class     = strpos($class, $component) !== false ? str_replace($component, ucfirst($component), $class) : $class;

		if ($this->getOption('suffix') && ! strripos($class, $component))
		{
			$class .= ucfirst($component);
		}

		// Trims input, normalize separators, and ensure that all paths are in Pascalcase.
		$class = ltrim(implode('\\', array_map('pascalize', explode('\\', str_replace('/', '\\', trim($class))))), '\\/');

		// Gets the namespace from input.
		$namespace = trim(str_replace('/', '\\', $this->getOption('namespace') ?? APP_NAMESPACE), '\\');

		if (strncmp($class, $namespace, strlen($namespace)) === 0)
		{
			return $class; // @codeCoverageIgnore
		}

		return $namespace . '\\' . $this->directory . '\\' . str_replace('/', '\\', $class);
	}

	/**
	 * Builds the file path from the class name.
	 *
	 * @param string $class
	 *
	 * @return string
	 */
	protected function buildPath(string $class): string
	{
		$namespace = trim(str_replace('/', '\\', $this->getOption('namespace') ?? APP_NAMESPACE), '\\');

		// Check if the namespace is actually defined and we are not just typing gibberish.
		$base = Services::autoloader()->getNamespace($namespace);

		if (! $base = reset($base))
		{
			// @codeCoverageIgnoreStart
			CLI::error(lang('CLI.namespaceNotDefined', [$namespace]), 'light_gray', 'red');
			CLI::newLine();

			return '';
			// @codeCoverageIgnoreEnd
		}

		$base = realpath($base) ?: $base;
		$file = $base . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, trim(str_replace($namespace, '', $class), '\\')) . '.php';

		return implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, $file), 0, -1)) . DIRECTORY_SEPARATOR . $this->basename($file);
	}

	/**
	 * Change file basename before saving.
	 *
	 * Useful for components where the file name has a date.
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	protected function basename(string $filename): string
	{
		return basename($filename);
	}

	/**
	 * Builds the contents for class being generated, doing all
	 * the replacements necessary, and alphabetically sorts the
	 * imports for a given template.
	 *
	 * @param string $class
	 *
	 * @return string
	 */
	protected function buildContent(string $class): string
	{
		$template = $this->prepare($class);

		if ($this->sortImports && preg_match('/(?P<imports>(?:^use [^;]+;$\n?)+)/m', $template, $match))
		{
			$imports = explode("\n", trim($match['imports']));
			sort($imports);

			return str_replace(trim($match['imports']), implode("\n", $imports), $template);
		}

		return $template;
	}



}
