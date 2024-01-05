<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rakoitde\Tools\GeneratorUpdateTrait;

class UpdateEntityCommand extends BaseCommand
{
    // use GeneratorUpdateTrait;

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
    protected $name = 'update:entity';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Updates an existing entity file.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'update:entity <name> [options]';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The entity class name.',
    ];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '--namespace'     => 'Set root namespace. Default: "APP_NAMESPACE".',
        '--suffix'        => 'Append the component title to the class name (e.g. User => UserModel).',
        '--useTimestamps' => 'Enable use of Timestamps and add missing fields to table',
        '--force'         => 'Force overwrite existing file and modify table if needed',
    ];

    protected $model;
    protected $modelInfo;
    protected $tableInfos;
    protected array $toReplace = [];
    protected $params;
    protected $table;

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        $this->params = $params;

        $model     = $this->getModel();
        $modelInfo = $this->modelInfo;
        // $entity    = $this->getEntity();
        $table = $this->getTable();

        CLI::write('Model: ' . CLI::color($modelInfo->name, 'white'), 'yellow');
        CLI::write('  Filename     : ' . CLI::color($modelInfo->filename, 'white'), 'green');
        CLI::write('  Table        : ' . CLI::color($model->table, 'white'), 'green');
        CLI::write('  PrimaryKey   : ' . CLI::color($modelInfo->primaryKeyMessage, $modelInfo->primaryKeyColor), 'green');
        CLI::write('  AllowedFields: ' . CLI::color($modelInfo->allowedFieldsMessage, 'white') .
            CLI::color($modelInfo->allowedFieldsNeedsUpdateMessage, 'red'), 'green');
        if ($modelInfo->allowedFieldsNeedsUpdate) {
            CLI::write('          toAdd: ' . CLI::color($modelInfo->missingFieldsMessage, 'blue'), 'green');
            CLI::write('       toRemove: ' . CLI::color($modelInfo->fieldsToRemoveMessage, 'red'), 'green');
        }
        CLI::write('  useTimestamps: ' . CLI::color($modelInfo->useTimestampsMessage, $modelInfo->useTimestampsColor), 'green');
        if ($this->model->useTimestamps) {
            CLI::write('  Fields needed: ' . CLI::color(implode(', ', $modelInfo->timestampFields), 'white'), 'green');
        }
        CLI::write('');

        CLI::write('Table: ' . CLI::color($table->table, 'white'), 'yellow');
        CLI::write('  Fields        : ' . CLI::color(implode(', ', $table->fields), 'white'), 'green');
        CLI::write('  PrimaryKey    : ' . CLI::color($table->primaryKey, 'white'), 'green');
        CLI::write('  AllowedFields : ' . CLI::color($table->allowedFieldsMessage, 'white'), 'green');

        $color = $this->forceUseTimestamps() ? 'red' : 'white';
        if (count($table->missingTimestampFields) > 0) {
            CLI::write('  missingTimestampFields : ' . CLI::color(implode(', ', $table->missingTimestampFields), $color), 'green');
        }
        CLI::write('');

        $this->addReplace("protected \$primaryKey = '{replace}';", $table->primaryKey);
        $this->addReplace('protected $allowedFields = [{replace}];', "'" . implode("', '", $table->allowedFields) . "'");

        if ($this->isForced() && $this->forceUseTimestamps()) {
            $this->addTimestampFields();
            $this->addReplace('protected $useTimestamps =\\s*{replace};', json_encode($this->forceUseTimestamps()));
        }

        if ($this->isForced()) {
            $this->replaceAll();
        }
    }

    protected function getModel()
    {
        $modelInfo = new class () {
            public string $namespace;
            public string $filename;
            public string $name;
            public array $timestampFields;
            public string $primaryKeyColor;
            public string $primaryKeyMessage;
            public string $allowedFieldsColor;
            public string $allowedFieldsMessage;
            public bool $allowedFieldsNeedsUpdate;
            public array $missigFields = [];
            public string $missigFieldsMessage;
            public array $fieldsToRemove = [];
            public string $fieldsToRemoveMessage;
            public string $useTimestampsColor;
            public string $useTimestampsMessage;
        };

        $modelInfo->namespace = $this->getOption('namespace') ?? 'App';

        $suffix          = $this->getOption('suffix') ? 'Model' : '';
        $modelInfo->name = $this->params[0] . $suffix;

        $this->model     = $model = model($modelInfo->name);
        $this->modelInfo = $modelInfo;

        $modelInfo->filename        = $this->getFilename();
        $modelInfo->timestampFields = [$model->createdField, $model->updatedField, $model->deletedField];

        $forceMessage = ' => Use --force for Update';

        $modelInfo->primaryKeyColor   = $model->primaryKey === '' ? 'red' : 'white';
        $modelInfo->primaryKeyMessage = $model->primaryKey !== '' ? $model->primaryKey : 'missing' . $forceMessage;

        $modelInfo->allowedFieldsColor   = count($model->allowedFields) === 0 ? 'red' : 'white';
        $allowedFields                   = "'" . implode("', '", $model->allowedFields) . "'";
        $modelInfo->allowedFieldsMessage = count($model->allowedFields) !== 0 ? $allowedFields : 'missing' . $forceMessage;

        $modelInfo->useTimestampsColor   = $model->useTimestamps !== $this->forceUseTimestamps() ? 'red' : 'white';
        $message                         = $model->useTimestamps !== $this->forceUseTimestamps() ? $forceMessage : '';
        $modelInfo->useTimestampsMessage = json_encode($model->useTimestamps) . $message;

        $this->modelInfo = $modelInfo;

        return $this->model;
    }

    protected function getTable()
    {
        $table = new class () {
            public $table;
            public $fields;
            public $primaryKey;
            public $allowedFields;
            public $allowedFieldsMessage;
            public $missingTimestampFields;
        };

        $model        = $this->model;
        $modelInfo    = $this->modelInfo;
        $forceMessage = ' => Use --force for Update';

        $table->table      = $model->table;
        $table->fields     = $model->db->getFieldNames($model->table);
        $table->primaryKey = $this->getPrimaryKey($model);

        if ($model->primaryKey !== '' && $model->primaryKey !== $table->primaryKey) {
            $modelInfo->primaryKeyColor   = 'red';
            $modelInfo->primaryKeyMessage = "'" . $model->primaryKey . "' is different" . $forceMessage;
        }

        $allowedFields = $table->fields;
        unset($allowedFields[array_search($table->primaryKey, $table->fields, true)], $allowedFields[array_search($model->createdField, $table->fields, true)], $allowedFields[array_search($model->updatedField, $table->fields, true)], $allowedFields[array_search($model->deletedField, $table->fields, true)]);

        $table->allowedFields        = $allowedFields;
        $table->allowedFieldsMessage = "'" . implode("', '", $allowedFields) . "'";

        $table->missingTimestampFields = array_diff($modelInfo->timestampFields, $table->fields);

        $modelInfo->missingFields         = array_diff($table->allowedFields, $model->allowedFields);
        $modelInfo->missingFieldsMessage  = "'" . implode("', '", $modelInfo->missingFields) . "'";
        $modelInfo->fieldsToRemove        = array_diff($model->allowedFields, $table->allowedFields);
        $modelInfo->fieldsToRemoveMessage = "'" . implode("', '", $modelInfo->fieldsToRemove) . "'";

        $modelInfo->fieldsAreFine        = array_diff($model->allowedFields, $modelInfo->fieldsToRemove);
        $modelInfo->fieldsAreFineMessage = "'" . implode("', '", $modelInfo->fieldsAreFine) . "'";

        $modelInfo->allowedFieldsNeedsUpdate        = count($modelInfo->missingFields) > 0 || count($modelInfo->fieldsToRemove) > 0;
        $modelInfo->allowedFieldsNeedsUpdateMessage = $modelInfo->allowedFieldsNeedsUpdate ? CLI::color(' => Use --force for Update', 'red') : '';

        $modelInfo->allowedFieldsMessage = $modelInfo->fieldsAreFineMessage;
        // $modelInfo->allowedFieldsMessage.= ",".CLI::color($modelInfo->missingFieldsMessage, 'blue');
        // $modelInfo->allowedFieldsMessage.= ",".CLI::color($modelInfo->fieldsToRemoveMessage, 'red');

        $this->table = $table;

        return $table;
    }

    protected function getFilename()
    {
        $source = service('autoloader')->getNamespace($this->modelInfo->namespace);

        return $source[0] . 'Models/' . $this->modelInfo->name . '.php';
    }

    protected function isForced(): bool
    {
        return $this->getOption('force') ? true : false;
    }

    protected function useTimestamps(): bool
    {
        return $this->model->useTimestamps;
    }

    protected function forceUseTimestamps(): bool
    {
        return $this->getOption('useTimestamps') ? true : false;
    }

    protected function getPrimaryKey($model): string
    {
        $fields = $model->db->getFieldData($model->table);

        foreach ($fields as $field) {
            if ($field->primary_key === true) {
                return $field->name;
            }
        }

        return '';
    }

    protected function addReplace($pattern, $value)
    {
        $replace = new class () {
            public string $pattern;
            public string $value;
        };
        $replace->pattern = $pattern;
        $replace->value   = $value;

        $this->toReplace[] = $replace;
    }

    protected function replaceAll()
    {
        CLI::write('Update Model: ' . CLI::color($this->modelInfo->name, 'white'), 'yellow');

        $contents = file_get_contents($this->modelInfo->filename);

        CLI::write('   Save Original: ' . CLI::color($this->modelInfo->filename . '.ori', 'white'), 'green');
        file_put_contents($this->modelInfo->filename . '.ori', $contents);

        $search_array  = [' ', '$', "'", '[', ']'];
        $replace_array = ['\\s*', '\$', "\\'", '\\[', '\\]'];

        foreach ($this->toReplace as $replace) {
            $replace->pattern = str_replace($search_array, $replace_array, $replace->pattern);
            $p                = explode('{replace}', $replace->pattern);
            $pattern          = "/({$p[0]})(.*)({$p[1]})/";
            $value            = '$1' . $replace->value . '$3';
            CLI::write('   Pattern: ' . CLI::color($pattern, 'yellow') . CLI::color(' => ' . $value, 'white'), 'green');
            $contents = preg_replace($pattern, $value, $contents);
        }

        file_put_contents($this->modelInfo->filename, $contents);
        CLI::write('');
    }

    protected function addTimestampFields()
    {
        $missingTimestampFields = $this->table->missingTimestampFields;

        CLI::write('Add Timestamp Columns: ' . CLI::color(implode(', ', $missingTimestampFields), 'white'), 'yellow');

        $forge = \Config\Database::forge();

        $fields = [];

        foreach ($missingTimestampFields as $field) {
            CLI::write('    ALTER TABLE `' . $this->model->table . '` ADD `' . $field . '` DATETIME');
            $fields[$field] = ['type' => 'DATETIME'];
        }

        CLI::write('');
        if (count($fields) === 0) {
            return;
        }

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
