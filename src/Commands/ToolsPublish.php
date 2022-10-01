<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Publisher\Publisher;
use Throwable;

class ToolsPublish extends BaseCommand
{
    protected $group       = 'Tools';
    protected $name        = 'tools:publish';
    protected $description = 'Publish Tools components into the current application.';

    protected $source;

    protected $published = [];

    public function run(array $params)
    {

        $this->source = service('autoloader')->getNamespace('Rakoitde\\Tools')[0];

        $this->publish();
        $this->replaceNamespaces();

    }

    private function publish()
    {

        $publisher = new Publisher($this->source, APPPATH);

        try {

            $publisher->addPaths(['Commands'])->retainPattern('*Command.php')->merge(); 
            $this->published = array_merge($this->published, $publisher->getPublished());

            $publisher->addPaths(['Entities'])->retainPattern('EntityRelationTrait.php')->merge();
            $this->published = array_merge($this->published, $publisher->getPublished());

        } catch (Throwable $e) {

            $this->showError($e);
            return;

        }

    }

    private function replaceNamespaces()
    {
        CLI::write('Replace namespaces in published files', 'yellow');
        foreach ($this->published as $file) {
            // Replace the namespace
            CLI::write('File: '.CLI::color($file, 'white'), 'yellow');
            $contents = file_get_contents($file);
            $contents = str_replace('namespace Rakoitde\\Tools', 'namespace ' . APP_NAMESPACE, $contents);
            file_put_contents($file, $contents);
        }
        CLI::write("");
    }


}