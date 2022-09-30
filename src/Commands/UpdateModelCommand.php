<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\RawSql;
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
        '--namespace'     => 'Set root namespace. Default: "APP_NAMESPACE".',
        '--useTimestamps' => 'Enable use of Timestamps and add fields to table',
        '--force'         => 'Force overwrite existing file.',
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

        $fields = $model->model->db->getFieldNames($model->table);
        $primaryKey = $this->getPrimaryKey($this->model->model);
        $allowedFields = $fields;
        unset($allowedFields[array_search($primaryKey, $fields)]);  # array_search('green', $array)
        unset($allowedFields[array_search($this->model->createdField, $fields)]);  # array_search('green', $array)
        unset($allowedFields[array_search($this->model->updatedField, $fields)]);  # array_search('green', $array)
        unset($allowedFields[array_search($this->model->deletedField, $fields)]);  # array_search('green', $array)

        CLI::write("{$model->table}: ", 'yellow');
        CLI::write("  Fields        : ".CLI::color(implode(", ", $fields), 'white'), 'green');
        CLI::write("  PrimaryKey    : ".CLI::color($primaryKey, 'white'), 'green');
        CLI::write("  AllowedFields : ".CLI::color(implode(", ", $allowedFields), 'white'), 'green');
        $missingTimestampFields = array_diff($this->model->timestampFields, $fields);
        CLI::write("  missingTimestampFields : ".CLI::color(implode(", ", $missingTimestampFields), 'white'), 'green');

        if ($this->isForced()) {
            CLI::write("Update Model: ", 'red');
            $contents = file_get_contents($this->model->filename);
            $contents = str_replace("protected \$primaryKey = '';", "protected \$primaryKey = '".$primaryKey."';", $contents);
            $contents = str_replace("protected \$allowedFields  = [];", "protected \$allowedFields  = ['".implode("', '",$allowedFields)."'];", $contents);
#var_dump($contents);
            file_put_contents($this->model->filename.".txt", $contents);
        }

        if ($this->useTimestamps()) {
            $this->addTimestampFields($missingTimestampFields);
        }

    }

    protected function getModel()
    {

        $this->model = new class {

            public string $namespace;           
            public string $table;
            public string $primaryKey;
            public array  $allowedFields;
            public string $filename;
            public bool $useTimestamps;
            public $createdField;
            public $updatedField;
            public $deletedField;
            public $model;

        };

        $this->model->namespace     = $this->getOption('namespace');
        $this->model->name          = $this->params[0]; #$this->getOption('namespace').'\\'.
        $model = model($this->model->name);
        $this->model->table         = $model->table;
        $this->model->primaryKey    = $model->primaryKey;
        $this->model->allowedFields = $model->allowedFields;
        $this->model->filename      = $this->getFilename(); 
        $this->model->useTimestamps = $model->useTimestamps;
        $this->model->createdField  = $model->createdField;
        $this->model->updatedField  = $model->updatedField;
        $this->model->deletedField  = $model->deletedField;
        $this->model->timestampFields = [$model->createdField, $model->updatedField, $model->deletedField];
#var_dump($this->model);
        $this->model->model         = $model;

        CLI::write("Model: ", 'yellow');
        CLI::write("  Filename     : ".CLI::color($this->model->filename, 'white'), 'green');
        CLI::write("  Table        : ".CLI::color($this->model->table, 'white'), 'green');
        CLI::write("  PrimaryKey   : ".CLI::color($this->model->primaryKey, 'white'), 'green');
        CLI::write("  AllowedFields: ".CLI::color(implode("', '", $this->model->allowedFields), 'white'), 'green');
        CLI::write("  useTimestamps: ".CLI::color(json_encode($this->model->useTimestamps), 'white'), 'green');
        CLI::write("         Fields: ".CLI::color(implode(", ", $this->model->timestampFields), 'white'), 'green');
        CLI::write("");

        return $this->model;
    }

    protected function getFilename()
    {
        $source = service('autoloader')->getNamespace($this->model->namespace);
        $filename = $source[0]."Models/".$this->model->name.".php";

        return $filename;
    }

    protected function isForced(): bool
    {
        return $this->getOption('force') ? true : false;
    }

    protected function useTimestamps(): bool
    {
        return $this->getOption('useTimestamps') ? true : false;
    }

    protected function getPrimaryKey($model): string
    {
        $fields = $model->db->getFieldData($model->table);

        foreach ($fields as $field) {
            if ($field->primary_key==true) { return $field->name; }
        }

        return '';
    }

    protected function addTimestampFields($missingTimestampFields)
    {

#'TIMESTAMP',
                #'default' => new RawSql('CURRENT_TIMESTAMP'),

        $forge = \Config\Database::forge();

        $fields = [];

        foreach ($missingTimestampFields as $field) {
            CLI::write("ALTER TABLE `".$this->model->table."` ADD `".$field."` DATETIME");
            $fields[$field] = ['type'    => 'DATETIME'];
        }

        if ( count($fields)==0 ) { return; }

        $forge->addColumn($this->model->table, $fields);
        // Executes: ALTER TABLE `table_name` ADD `preferences` TEXT
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
