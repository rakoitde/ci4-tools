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

    protected $source;

    public function run(array $params)
    {

        $this->source = service('autoloader')->getNamespace('Rakoitde\\Tools')[0];

        $this->publishCommands();

    }

    private function publish()
    {

        $publisher = new Publisher($this->source, APPPATH);

        try {
            $publisher->addPaths(['Commands/*Command.php','Entities/*.php'])->merge(false); // Be careful not to overwrite anything
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

    private function ____publishEntityRelationTrait()
    {

        $publisher = new Publisher($this->source, APPPATH);

        try {
            $publisher->addPaths(['Entities/*.php'])->merge(false); // Be careful not to overwrite anything
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