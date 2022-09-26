<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\Publisher\Publisher;
use Throwable;

class ToolsPublish extends BaseCommand
{
    protected $group       = 'Tools';
    protected $name        = 'Tools:publish';
    protected $description = 'Publish Tools components into the current application.';

    public function run(array $params)
    {
        // Use the Autoloader to figure out the module path
        $source = service('autoloader')->getNamespace('Rakoitde\\Tools');

        $publisher = new Publisher($source, APPPATH);

        try {
            // Add only the desired components
            $publisher->addPaths([
                'Commands/*Command.php',
            ])->merge(false); // Be careful not to overwrite anything
        } catch (Throwable $e) {
            $this->showError($e);

            return;
        }

        // If publication succeeded then update namespaces
        foreach ($publisher->getPublished() as $file) {
            // Replace the namespace
            $contents = file_get_contents($file);
            $contents = str_replace('namespace Rakoitde\\Tools', 'namespace ' . APP_NAMESPACE, $contents);
            file_put_contents($file, $contents);
        }
    }
}