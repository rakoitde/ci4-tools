<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Autoload;
use Rakoitde\Tools\GeneratorUpdateTrait;

class UpdateModelCommand extends BaseCommand
{

    #use GeneratorUpdateTrait;

    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'update:model';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = '';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'update:model [arguments] [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'name' => 'The model class name.',
    ];

    protected $options = [
        '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".',
        '--force'     => 'Force overwrite existing file.',
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {

        $this->params = $params;

        $model = $this->getModel();
        $filename = $this->getFilename();

        $dbGroup = $this->getOption('dbgroup');
        $return  = $this->getOption('return');
        CLI::write("Model: ", 'yellow');
        CLI::write("  Filename     : ".CLI::color($filename, 'white'), 'green');
        CLI::write("  Table        : ".CLI::color($model->table, 'white'), 'green');
        CLI::write("  PrimaryKey   : ".CLI::color($model->primaryKey, 'white'), 'green');
        CLI::write("  AllowedFields: ".CLI::color(json_encode($model->allowedFields), 'white'), 'green');


        $fields = $model->db->getFieldNames($model->table);
        $primaryKey = $this->getPrimaryKey($model);
        $allowedFields = $fields;
        unset($allowedFields[array_search($primaryKey, $fields)]);  # array_search('green', $array)

        CLI::write("{$model->table}: ", 'yellow');
        CLI::write("  Fields        : ".CLI::color(implode(", ", $fields), 'white'), 'green');
        CLI::write("  PrimaryKey    : ".CLI::color($primaryKey, 'white'), 'green');
        CLI::write("  AllowedFields : ".CLI::color(implode(", ", $allowedFields), 'white'), 'green');

        if ($this->isForced()) {
            CLI::write("Update Model: ", 'red');
            $contents = file_get_contents($filename);
            $contents = str_replace("protected \$primaryKey = '';", "protected \$primaryKey = '".$primaryKey."';", $contents);
            $contents = str_replace("protected \$allowedFields  = [];", "protected \$allowedFields  = ['".implode("', '",$allowedFields)."'];", $contents);

            file_put_contents($filename.".txt", $contents);
        }

    }

    protected function getModel()
    {
        $modelname = $this->params[0];
        $namespace = $this->getOption('namespace');
        $modelname = $namespace.'\\'.$modelname;
        return model($modelname);
    }

    protected function getFilename()
    {
        $modelname = $this->params[0];
        $namespace = $this->getOption('namespace');
        $source = service('autoloader')->getNamespace("App");
        $filename = $source[0]."Models/".$modelname.".php";

        return $filename;
    }

    protected function isForced(): bool
    {
        return $this->getOption('force') ? true : false;
    }

    protected function getPrimaryKey($model): string
    {
        $fields = $model->db->getFieldData($model->table);

        foreach ($fields as $field) {
            if ($field->primary_key==true) { return $field->name; }
        }

        return '';
    }

    /**
     * Gets a single command-line option. Returns TRUE if the option exists,
     * but doesn't have a value, and is simply acting as a flag.
     *
     * @return mixed
     */
    protected function getOption(string $name)
    {
        if (! array_key_exists($name, $this->params)) {
            return CLI::getOption($name);
        }

        return $this->params[$name] ?? true;
    }
}
