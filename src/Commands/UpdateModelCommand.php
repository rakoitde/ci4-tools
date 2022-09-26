<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
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


        $dbGroup = $this->getOption('dbgroup');
        $return  = $this->getOption('return');
        CLI::write("Table: ".$model->table);
        CLI::write("PrimaryKey: ".$model->primaryKey);
        CLI::write("AllowedFields: ".json_encode($model->allowedFields));



        CLI::write("FieldsInTable {$model->table}: ".json_encode($model->db->getFieldNames($model->table)));

    }

    protected function getModel()
    {
        $modelname = $this->params[0];
        $namespace = $this->getOption('namespace');
        $modelname = $namespace.'\\'.$modelname;
        return model($modelname);
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
