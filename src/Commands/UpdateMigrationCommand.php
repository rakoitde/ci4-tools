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

class UpdateMigrationCommand extends BaseCommand
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
    protected $name = 'update:migration';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Updates an existing migration file.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'update:migration <name> [options]';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The model class name.',
    ];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '--namespace'               => 'Set root namespace. Default: "APP_NAMESPACE".',
        '--suffix'                  => 'Append the component title to the class name (e.g. User => UserModel).',
        '--createTable'             => 'Updates the first migration file with create table statements',
        '--alterTable'              => 'Updates the last migration file with alter table statements',
        '--disableForeignKeyChecks' => 'Temporarily bypass the foreign key checks while running migrations',
        '--force'                   => 'Force overwrite existing file and modify table if needed',
    ];

    // $this->db->disableForeignKeyChecks();
    protected $model;
    protected $modelInfo;
    protected $tableInfos;
    protected array $toReplace  = [];
    protected string $up        = '';
    protected string $down      = '';
    protected array $migrations = [];
    protected array $replace;
    protected $params;
    protected $table;

    /**
     * Actually execute a command.
     */
    public function run(array $params)
    {
        if (count($params) === 0) {
            CLI::write('The model class name as first argument is missing', 'red');

            exit;
        }

        $this->params = $params;

        $model = $this->getModel();
        $this->getMigrations();
        $m = count($this->migrations);

        CLI::write('');
        CLI::write(CLI::color((string) $m, 'green') . ' migration files found.', 'white');
        CLI::write(' [0] ' . CLI::color('abort', 'green'), 'green');

        $choices = [''];

        if ($m === 0) {
            CLI::write(' [1] ' . CLI::color('create migration file and update with create table', 'white'), 'green');
            $choices = ['', '0', '1'];
        }
        if ($m === 1) {
            CLI::write(' [1] ' . CLI::color('update 1st file (' . $this->migrations[0]->version . ') with create table', 'white'), 'green');
            CLI::write(' [2] ' . CLI::color('create 2nd file and update with alter table', 'white'), 'green');
            $choices = ['', '0', '1', '2'];
        }
        if ($m === 2) {
            CLI::write(' [1] ' . CLI::color('update 1st file (' . $this->migrations[0]->version . ') with create table and remove the rest', 'white'), 'green');
            CLI::write(' [2] ' . CLI::color('update 2nd file (' . $this->migrations[1]->version . ') with alter table', 'white'), 'green');
            $choices = ['', '0', '1', '2'];
        }
        if ($m > 2) {
            CLI::write(' [1] ' . CLI::color('update 1st file (' . $this->migrations[0]->version . ') with create table and remove the rest', 'white'), 'green');
            CLI::write(' [2] ' . CLI::color('update 2nd file (' . $this->migrations[1]->version . ') with alter table and remove the rest', 'white'), 'green');
            CLI::write(' [3] ' . CLI::color('update last file (' . $this->lastMigrationFile()->version . ') with alter table', 'white'), 'green');
            $choices = ['', '0', '1', '2', '3'];
        }

        CLI::write('');

        do {
            $choice = CLI::input('Make youre choise ' . CLI::color('[0]', 'green') . ': ');
        } while (! in_array(trim($choice), $choices, true));

        if (in_array($choice, ['', '0'], true)) {
            exit;
        }

        if ($choice == '1') {
            $this->updateFirstMigrationFile();
        }

        if ($choice === '2') {
            // Get the contents of the JSON file
            $file1 = file_get_contents('/Applications/MAMP/htdocs/ci4test/rakoitde/Test/Database/Migrations/2022-10-03-064801_TestUserMigration.json');
            $file2 = file_get_contents('/Applications/MAMP/htdocs/ci4test/rakoitde/Test/Database/Migrations/2022-10-04-064801_TestUserMigration.json');
            // Convert to array
            $json1 = json_decode($file1, true);
            $json2 = json_decode($file2, true);

            // $this->saveTableInfoAsJson($this->migrations[1]);
        }

        CLI::write('Namespace: ' . CLI::color($this->modelInfo->namespace, 'white'), 'yellow');
        CLI::write('Model:     ' . CLI::color($this->modelInfo->name, 'white'), 'yellow');
        CLI::write('Namespace: ' . CLI::color($this->modelInfo->namespace . '\Database\Migrations\\', 'white'), 'yellow');

        if ($this->getOption('createTable') && ! $this->getOption('alterTable')) {
            CLI::write('Updates the first migration file with create table statements', 'yellow');
        }

        if (! $this->getOption('createTable') && $this->getOption('alterTable')) {
            CLI::write('Updates the last migration file with alter table statements', 'yellow');
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
        };

        $modelInfo->namespace = $this->getOption('namespace') ?? 'App';

        $suffix          = $this->getOption('suffix') ? 'Model' : '';
        $modelInfo->name = $this->params[0] . $suffix;

        $this->model = model($modelInfo->name);

        $this->modelInfo = $modelInfo;

        return $this->model;
    }

    protected function getMigrations()
    {
        $m          = new \CodeIgniter\Database\MigrationRunner(config('Migrations'));
        $migrations = $m->findNamespaceMigrations($this->modelInfo->namespace);

        $suffix = $this->getOption('suffix') ? 'Migration' : '';
        $name   = $this->params[0] . $suffix;
        CLI::write('Migration Name: ' . CLI::color($name, 'white'), 'yellow');

        foreach ($migrations as $migration) {
            if ($migration->name === $name) {
                $this->migrations[] = $migration;
            }
        }
    }

    protected function firstMigrationFile()
    {
        return $this->migrations[0];
    }

    protected function lastMigrationFile()
    {
        return $this->migrations[count($this->migrations) - 1];
    }

    protected function updateFirstMigrationFile()
    {
        $suffix = $this->getOption('suffix') ? 'Migration' : '';
        $name   = $this->params[0] . $suffix;
        if (count($this->migrations) === 0) {
            $namespace = str_replace('\\', '\\\\', $this->modelInfo->namespace);
            $command   = 'make:migration ' . $name . ' --namespace "' . $namespace . '"';
            CLI::write("Command: " . $command);
            $result    = command($command);
            $this->getMigrations();
        }

        $this->parseUpForCreateTable();
        CLI::write('Update: ' . CLI::color($this->migrations[0]->version . '_' . $this->migrations[0]->name, 'white'), 'yellow');

        $this->updateMigrationFile($this->migrations[0]);
        $this->saveTableInfoAsJson($this->migrations[0]);
    }

    protected function updateMigrationFile($migration)
    {
        $up   = $this->getUp();
        $down = $this->getDown();

        $this->addReplace('(<.*class\s\w*\sextends\sMigration.\{.)(.*)(.\})', $up . $down);

        $this->replaceAll($migration);
    }

    protected function saveTableInfoAsJson($migration)
    {
        $json_file = str_replace('.php', '.json', $migration->path);

        $data = [
            'table'       => $this->model->table,
            'fields'      => $this->model->db->getFieldData($this->model->table),
            'foreignkeys' => $this->model->db->getForeignKeyData($this->model->table),
        ];

        CLI::write('saveTableInfoAsJson: ' . $json_file);

        file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function parseUpForCreateTable()
    {
        $this->disableForeignKeyChecks();

        $this->parseUpFields();
        $this->parseUpKeys();
        $this->parseUpTable();
        $this->enableForeignKeyChecks();

        $this->parseDownTable();
    }

    protected function getUp()
    {
        $i = '        ';

        $up = PHP_EOL;
        $up .= '    public function up()' . PHP_EOL;
        $up .= '    {' . PHP_EOL;
        $up .= PHP_EOL;
        $up .= $this->up;
        $up .= '    }' . PHP_EOL;
        $up .= PHP_EOL;

        return $up;
    }

    protected function getDown()
    {
        $i = '        ';

        $down = PHP_EOL;
        $down .= '    public function down()' . PHP_EOL;
        $down .= '    {' . PHP_EOL;
        $down .= PHP_EOL;
        $down .= $this->down;
        $down .= '    }' . PHP_EOL;
        $down .= PHP_EOL;

        return $down;
    }

    protected function parseUpFields()
    {
        $i = '        ';

        $up = $i . '$this->forge->addField([' . PHP_EOL;

        $fields = $this->model->db->getFieldData($this->model->table);

        foreach ($fields as $field) {
            $up .= $i . "    '{$field->name}' => [" . PHP_EOL;
            $up .= $i . "        'type'           => '{$field->type}'," . PHP_EOL;
            if ($field->max_length) {
                $up .= $i . "        'constraint'     => {$field->max_length}," . PHP_EOL;
            }
            // if ($field->unsigned) {
            //     $up.= $i."        'unsigned'       => true,".PHP_EOL;
            // }
            if ($field->nullable) {
                $up .= $i . "        'nullable'       => true," . PHP_EOL;
            }
            if (null !== $field->default) {
                $up .= $i . "        'default'        => '{$field->default}'," . PHP_EOL;
            }
            if ($field->primary_key === 1) {
                $up .= $i . "        'auto_increment' => true," . PHP_EOL;
            }
            $up .= $i . '    ],' . PHP_EOL;
        }
        $up .= $i . ']);' . PHP_EOL;

        $this->up .= $up . PHP_EOL;

        // $this->forge->addField([
        //     'blog_id' => [
        //         'type'           => 'INT',
        //         'constraint'     => 5,
        //         'unsigned'       => true,
        //         'auto_increment' => true,
        //     ],
        //     'blog_title' => [
        //         'type'       => 'VARCHAR',
        //         'constraint' => '100',
        //     ],
        //     'blog_description' => [
        //         'type' => 'TEXT',
        //         'null' => true,
        //     ],
        // ]);
    }

    protected function parseUpKeys()
    {
        $i = '        ';

        $fields = $this->model->db->getFieldData($this->model->table);

        $up = '';

        foreach ($fields as $field) {
            if ($field->primary_key === 1) {
                $up = $i . "\$this->forge->addKey('" . $field->name . "', true);" . PHP_EOL;
            }
        }

        $this->up .= $up . PHP_EOL;
        // stdClass Object
        // (
        //     [name] => id
        //     [type] => int
        //     [max_length] => 11
        //     [nullable] =>
        //     [default] =>
        //     [primary_key] => 1
        // )

        // $this->forge->addKey('blog_id', true);
    }

    protected function parseUpTable()
    {
        $i = '        ';
        $this->up .= $i . "\$this->forge->createTable('" . $this->model->table . "');" . PHP_EOL . PHP_EOL;
    }

    protected function parseDownTable()
    {
        $i = '        ';
        $this->down .= $i . "\$this->forge->dropTable('" . $this->model->table . "');" . PHP_EOL . PHP_EOL;
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

    protected function replaceAll($migration)
    {
        CLI::write('Update Migration: ' . CLI::color($migration->version . '_' . $migration->name, 'white'), 'yellow');

        $contents = file_get_contents($migration->path);

        // CLI::write("   Save Original: ".CLI::color($this->modelInfo->filename.".ori","white"), "green");
        // file_put_contents($this->modelInfo->filename.".ori", $contents);

        // $search_array  = [" "  , "$"  , "'"  , "["  , "]"  , "{"  , "}"  ];
        // $replace_array = ["\s*", "\\$", "\\'", "\\[", "\\]", "\\{", "\\}"];

        foreach ($this->toReplace as $replace) {
            // $replace->pattern = str_replace($search_array, $replace_array, $replace->pattern);
            // $p = explode("\\{replace\\}", $replace->pattern);
            // $pattern = "/({$p[0]})(.*)({$p[1]})/";
            $pattern = '/' . $replace->pattern . '/sm';
            $value   = '$1' . $replace->value . '$3';
            // CLI::write("   Pattern: ".CLI::color($pattern, 'yellow').CLI::color(' => '.$value, "white"), 'green');
            $contents = preg_replace($pattern, $value, $contents);
        }

        file_put_contents($migration->path, $contents);
        CLI::write('');
    }

    protected function disableForeignKeyChecks()
    {
        if (! $this->getOption('disableForeignKeyChecks')) {
            return;
        }
        $i = '        ';
        $this->up .= $i . '$this->db->disableForeignKeyChecks();' . PHP_EOL . PHP_EOL;
    }

    protected function enableForeignKeyChecks()
    {
        if (! $this->getOption('disableForeignKeyChecks')) {
            return;
        }
        $i = '        ';
        $this->up .= $i . '$this->db->enableForeignKeyChecks();' . PHP_EOL . PHP_EOL;
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

    protected function array_diff_assoc_recursive($array1, $array2)
    {
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (! isset($array2[$key])) {
                    $difference[$key] = $value;
                } elseif (! is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);
                    if ($new_diff !== false) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (! array_key_exists($key, $array2) || $array2[$key] !== $value) {
                $difference[$key] = $value;
            }
        }

        return ! isset($difference) ? 0 : $difference;
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
