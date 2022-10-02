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
        '--suffix'        => 'Append the component title to the class name (e.g. User => UserModel).',
        '--force'         => 'Force overwrite existing file.',
    ];

    protected $model;

    protected array $toReplace = [];

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

        CLI::write("Table: ".CLI::color($model->table, 'white'), 'yellow');
        CLI::write("  Fields        : ".CLI::color(implode(", ", $fields), 'white'), 'green');
        CLI::write("  PrimaryKey    : ".CLI::color($primaryKey, 'white'), 'green');
        CLI::write("  AllowedFields : ".CLI::color(implode(", ", $allowedFields), 'white'), 'green');
        $missingTimestampFields = array_diff($this->model->timestampFields, $fields);

        $color = $this->forceUseTimestamps() ? "red" : "white";
        if (count($missingTimestampFields)>0) {
            CLI::write("  missingTimestampFields : ".CLI::color(implode(", ", $missingTimestampFields), $color), 'green');
        }
        CLI::write("");

        $this->addReplace("protected \$primaryKey = '{replace}';",    $primaryKey);
        $this->addReplace("protected \$allowedFields = [{replace}];", "'".implode("', '",$allowedFields)."'");

        if ($this->isForced() && $this->forceUseTimestamps()) {
            $this->addTimestampFields($missingTimestampFields);
            $this->addReplace("protected \$useTimestamps =\s*{replace};", json_encode($this->forceUseTimestamps()));
        }

        if ($this->isForced()) { $this->replaceAll(); }

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

        $suffix = $this->getOption('suffix') ? "Model" : "";
        $this->model->name          = $this->params[0].$suffix; #$this->getOption('namespace').'\\'.
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
        $this->model->model         = $model;

        CLI::write("Model: ".CLI::color($this->model->name, 'white'), 'yellow');
        CLI::write("  Filename     : ".CLI::color($this->model->filename, 'white'), 'green');
        CLI::write("  Table        : ".CLI::color($this->model->table, 'white'), 'green');
        
        $color = $this->model->primaryKey == '' ? 'red' : 'white';
        $primaryKey = $this->model->primaryKey != '' ? $this->model->primaryKey : 'missing => Use --force for Update';
        CLI::write("  PrimaryKey   : ".CLI::color($primaryKey, $color), 'green');
        
        $color = count($this->model->allowedFields)==0 ? 'red' : 'white';
        $allowedFields = count($this->model->allowedFields)!=0 ? "'".implode("', '", $this->model->allowedFields)."'" : 'missing => Use --force for Update';
        CLI::write("  AllowedFields: ".CLI::color($allowedFields, $color), 'green');

        $color = $this->model->useTimestamps != $this->forceUseTimestamps() ? 'red' : 'white';
        $message = $this->model->useTimestamps != $this->forceUseTimestamps() ? ' => Use --force for Update' : '';
        CLI::write("  useTimestamps: ".CLI::color(json_encode($this->model->useTimestamps).$message, $color), 'green');
        if ($this->model->useTimestamps) {
            CLI::write("  Fields needed: ".CLI::color(implode(", ", $this->model->timestampFields), 'white'), 'green');
        }
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
        return $this->model->model->useTimestamps;
    }

    protected function forceUseTimestamps(): bool
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

    protected function addReplace($pattern, $value)
    {
        $replace = new class {
            public string $pattern;
            public string $value;
        };
        $replace->pattern = $pattern;
        $replace->value   = $value;

        $this->toReplace[] = $replace;
    }

    protected function replaceAll()
    {
        CLI::write("Update Model: ".CLI::color($this->model->name, 'white'), 'yellow');

        $contents = file_get_contents($this->model->filename);

        CLI::write("   Save Original: ".CLI::color($this->model->filename.".ori","white"), "green");
        file_put_contents($this->model->filename.".ori", $contents);

        $search_array  = [" "  , "$"  , "'"  , "["  , "]"  ];
        $replace_array = ["\s*", "\\$", "\\'", "\\[", "\\]"];

        foreach ($this->toReplace as $replace) {
            $replace->pattern = str_replace($search_array, $replace_array, $replace->pattern);
            $p = explode("{replace}", $replace->pattern);
            $pattern = "/({$p[0]})(.*)({$p[1]})/";
            $value = '$1'.$replace->value.'$3';
            CLI::write("   Pattern: ".CLI::color($pattern, 'yellow').CLI::color(' => '.$value, "white"), 'green');
            $contents = preg_replace($pattern, $value, $contents);
        }

        file_put_contents($this->model->filename, $contents);
        CLI::write("");
    }

    protected function addTimestampFields($missingTimestampFields)
    {
        CLI::write("Add Timestamp Columns: ".CLI::color(implode(", ", $missingTimestampFields), 'white'), 'yellow');

        $forge = \Config\Database::forge();

        $fields = [];

        foreach ($missingTimestampFields as $field) {
            CLI::write("    ALTER TABLE `".$this->model->table."` ADD `".$field."` DATETIME");
            $fields[$field] = ['type' => 'DATETIME'];
        }

        CLI::write("");
        if ( count($fields)==0 ) { return; }

        $forge->addColumn($this->model->table, $fields); // Executes: ALTER TABLE `table_name` ADD `preferences` TEXT

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
