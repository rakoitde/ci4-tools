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
use Swaggest\JsonDiff\JsonDiff;

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
    protected string $i = '        ';

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
            CLI::write(' [3] ' . CLI::color('create new file and update with alter table', 'white'), 'green');
            $choices = ['', '0', '1', '3'];
        }
        if ($m >= 2) {
            CLI::write(' [1] ' . CLI::color('update 1st file (' . $this->migrations[0]->version . ') with create table and remove the rest', 'white'), 'green');
            CLI::write(' [2] ' . CLI::color('update last file (' . $this->lastMigrationFile()->version . ') with alter table', 'white'), 'green');
            CLI::write(' [3] ' . CLI::color('create new file and update with alter table', 'white'), 'green');
            $choices = ['', '0', '1', '2', '3'];
        }

        CLI::write('');

        do {
            $choice = trim(CLI::input('Make youre choise ' . CLI::color('[0]', 'green') . ': '));
        } while (! in_array($choice, $choices, true));

        if (in_array($choice, ['', '0'], true)) {
            exit;
        }

        if ($choice == '1') {
            $this->updateFirstMigrationFile();
        }

        if ($choice === '2') {

            $lastMigrationIndex = count($this->migrations)-2;
            $lastMigrationFile = $this->migrations[$lastMigrationIndex] ?? null;

            $jsonDiff = $this->getTableStructureDiff($lastMigrationFile);
            $this->parseDiff($jsonDiff);

            $suffix = $this->getOption('suffix') ? 'Migration' : '';
            $name   = str_replace('Migration', '', $this->migrations[0]->name) . $suffix . $choice;

            if (null === $lastMigrationFile) {
                command("make:migration {$name} --table {$this->table}");
                $this->getMigrations();
            }

            $lastMigrationIndex = count($this->migrations)-1;
            $lastMigrationFile = $this->migrations[$lastMigrationIndex];

            if (null !== $lastMigrationFile) {
                $this->updateMigrationFile($lastMigrationFile);
                $this->saveTableInfoAsJson($lastMigrationFile);
            }
        }

        if ($choice === '3') {

            $lastMigrationFile = $this->lastMigrationFile() ?? null;

            $jsonDiff = $this->getTableStructureDiff($lastMigrationFile);
            $this->parseDiff($jsonDiff);

            $suffix = $this->getOption('suffix') ? 'Migration' : '';
            $migrationCount = count($this->migrations) + 1;
            $name   = str_replace('Migration', '', $this->migrations[0]->name) . $suffix . $migrationCount;
            
            command("make:migration {$name} --table {$this->table}");
            $this->getMigrations();
            
            $lastMigrationFile = $this->lastMigrationFile() ?? null;
            
            if (null !== $lastMigrationFile) {
                $this->updateMigrationFile($lastMigrationFile);
                $this->saveTableInfoAsJson($lastMigrationFile);
            }
        }

        CLI::write('Namespace: ' . CLI::color($this->modelInfo->namespace, 'white'), 'yellow');
        CLI::write('Model:     ' . CLI::color($this->modelInfo->name,      'white'), 'yellow');
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

        if (null === $this->model) {
            CLI::write('The Model ' . $modelInfo->name . ' not exists.', 'red');
            exit;
        }

        $this->modelInfo = $modelInfo;

        return $this->model;
    }

    protected function getMigrations()
    {

        $this->migrations = [];

        $m          = new \CodeIgniter\Database\MigrationRunner(config('Migrations'));
        $migrations = $m->findNamespaceMigrations($this->modelInfo->namespace);
        $suffix = $this->getOption('suffix') ? 'Migration' : '';
        $name   = $this->params[0] . $suffix;
        CLI::write('Migration Name: ' . CLI::color($name, 'white'), 'yellow');

        foreach ($migrations as $migration) {
            if (str_starts_with($migration->name, $name)) {
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
            'indexes'     => $this->model->db->getIndexData($this->model->table),
            'foreignkeys' => $this->model->db->getForeignKeyData($this->model->table),
        ];

        CLI::write('saveTableInfoAsJson: ' . $json_file);

        file_put_contents($json_file, $this->createJsonInfo());
    }

    protected function getTableStructureDiff($migration)
    {
        #$originalFilePath = str_replace('.php', '.json', $this->migrations[0]->path);
        $originalFilePath = str_replace('.php', '.json', $migration->path);

        // Get the contents of the JSON file
        $originalFile = file_get_contents($originalFilePath);

        // Convert to array
        $originalJson = json_decode($originalFile, true);

        // Generate Diff
        $jsonDiff = new JsonDiff(
            $originalJson,
            json_decode($this->createJsonInfo())
        );

        return $jsonDiff;
    }

    protected function parseDiff($jsonDiff) {

        $rearranged = $jsonDiff->getRearranged();

        // Modified
        foreach ($jsonDiff->getModifiedNew() ?? [] as $type => $array) {

            CLI::write("Modified {$type}:", "yellow");

            $this->up .= match ($type) {
                'fields' => $this->parseUpModifyColumns($array, $rearranged),
                default => $type . "\n",
            };

        }

        // Add
        foreach ($jsonDiff->getAdded() ?? [] as $type => $array) {

            CLI::write("Added {$type}:", "yellow");

            // ##### UP ##### 

            $this->up .= match ($type) {
                'fields' => $this->parseAddColumns($array, $rearranged),
                default => '',
            };
            
            $this->up .= match ($type) {
                'foreignkeys' => $this->parseAddForeignKeys($array),
                default => '',
            };
            
            $this->up .= match ($type) {
                'indexes' => $this->parseAddKeys($array),
                default => '',
            };
            
            // ##### DOWN ##### 
            
            $this->down .= match ($type) {
                'fields' => $this->parseDropColumns($array, $rearranged),
                default => '',
            };
            
            $this->down .= match ($type) {
                'indexes' => $this->parseDropKeys($array),
                default => '',
            };
            
        }
        
        // Removed
        foreach ($jsonDiff->getRemoved() ?? [] as $type => $array) {
            
            CLI::write("Removed {$type}:", "yellow");
            
            // ##### UP ##### 
            
            $this->up .= match ($type) {
                'fields' => $this->parseDropColumns($array, $rearranged),
                default => '',
            };
            
            $this->up .= match ($type) {
                'indexes' => $this->parseDropKeys($array),
                default => '',
            };
            
            // ##### DOWN ##### 
            
            $this->down .= match ($type) {
                'fields' => $this->parseAddColumns($array, $rearranged),
                default => '',
            };
            
            $this->down .= match ($type) {
                'indexes' => $this->parseAddKeys($array),
                default => '',
            };

        }

    }

    protected function parseUpModifyColumns($modified, $rearranged)
    {

        $fields = [];

        foreach ($modified as $field => $attr) {
            $fields[] = $rearranged->fields->$field;
            CLI::write("Field: {$field}: " . json_encode($rearranged->fields->$field));
        }

        $this->up .= $this->i . '$this->forge->modifyColumn(\'' . $this->model->table . '\', [' . "\n";
        $this->parseUpFields($fields);
        $this->up .= $this->i . ']);' . "\n";

        return $this->up;
    }

    protected function parseAddColumns($modified, $rearranged)
    {

        $str = '';

        foreach ($modified as $fieldName) {
            $field = $rearranged->fields->$fieldName;
            CLI::write("Field: {$fieldName}: " . json_encode($rearranged->fields->$field));

            $str .= $this->i . '$this->forge->addColumn(\'' . $this->model->table . '\', [' . "\n";

            $str .= $this->i . "    '{$field->name}' => [" . "\n";
            $str .= $this->i . "        'type'           => '{$field->type}'," . "\n";
            if ($field->max_length) {
                $str .= $this->i . "        'constraint'     => {$field->max_length}," . "\n";
            }
            // if ($field->unsigned) {
            //     $up.= $i."        'unsigned'       => true,".PHP_EOL;
            // }
            if ($field->nullable) {
                $str .= $this->i . "        'null'           => true," . "\n";
            }
            if (null !== $field->default) {
                $str .= $this->i . "        'default'        => '{$field->default}'," . "\n";
            }
            if ($field->primary_key === 1) {
                $str .= $this->i . "        'auto_increment' => true," . "\n";
            }
            $str .= $this->i . '    ],' . "\n";
        }

        $str .= $this->i . ']);' . "\n";

        return $str;
    }

    protected function parseDropColumns($modified, $rearranged)
    {

        $down = '';

        foreach ($modified as $field) {
            CLI::write("Field: {$field}: " . json_encode($rearranged->fields->$field));
            $down .= $this->i . '$this->forge->dropColumn(\'' . $this->model->table . '\', \'' . $field . '\');' . "\n";
        }


        return $down;
    }

    protected function parseAddForeignKeys($foreignkeys)
    {
        dd($foreignkeys);

        // "FK_workflow_instance_workflow": {
        //     "constraint_name": "FK_workflow_instance_workflow",
        //     "table_name": "workflow_instance",
        //     "column_name": [
        //         "workflow_id"
        //     ],
        //     "foreign_table_name": "workflow",
        //     "foreign_column_name": [
        //         "id"
        //     ],
        //     "on_delete": "CASCADE",
        //     "on_update": "CASCADE",
        //     "match": "NONE"
        // }

        $up = '';

        foreach ($foreignkeys as $foreignkey) {

            // addForeignKey($fieldName, $tableName, $tableField[, $onUpdate = '', $onDelete = '', $fkName = ''])
            // $forge->addForeignKey(['users_id', 'users_name'], 'users', ['id', 'name'], 'CASCADE', 'CASCADE', 'my_fk_name');
            // gives CONSTRAINT `my_fk_name` FOREIGN KEY(`users_id`, `users_name`) REFERENCES `users`(`id`, `name`) ON DELETE CASCADE ON UPDATE CASCADE

            $up .= $this->i . "\$this->forge->addForeignKey(['" . implode("', '", $foreignkey->column_name) . "'], '" . $foreignkey->foreign_table_name . "', '[" . implode("', '", $foreignkey->foreign_column_name) . "]', '" . $foreignkey->on_delete . "', '" . $foreignkey->on_update . "', '" . $foreignkey->constraint_name . "');" . "\n";

        }

        $up = '';

        return $up . "\n";

    }

    protected function createJsonInfo()
    {

        $fields = [];

        foreach ($this->model->db->getFieldData($this->model->table) as $field) {
            $fields[$field->name] = $field;
        }

        $data = [
            'table'       => $this->model->table,
            'fields'      => $fields,
            'indexes'     => $this->model->db->getIndexData($this->model->table),
            'foreignkeys' => $this->model->db->getForeignKeyData($this->model->table),
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    protected function parseUpForCreateTable()
    {
        $this->disableForeignKeyChecks();

        $fields = $this->model->db->getFieldData($this->model->table);

        $this->up .= $this->i . '$this->forge->addField([' . "\n";
        $this->parseUpFields($fields);
        $this->up .= $this->i . ']);' . "\n";
        #$this->parseUpFields($fields, 'addField');
        $this->parseUpKeys();
        $this->parseUpForeignkeys();
        $this->parseUpTable();
        $this->enableForeignKeyChecks();

        $this->parseDownTable();
    }

    protected function getUp()
    {

        $up = '';
        $up .= '    public function up()' . "\n";
        $up .= '    {' . "\n";
        $up .= $this->up;
        $up .= '    }' . "\n";

        return $up;
    }

    protected function getDown()
    {

        $down = "\n";
        $down .= '    public function down()' . "\n";
        $down .= '    {' . "\n";
        $down .= $this->down;
        $down .= '    }';

        return $down;
    }

    protected function parseUpFields($fields)
    {
        $up = '';
        #$up = $this->i . '$this->forge->' . $function . '([' . "\n";

        #$fields = $this->model->db->getFieldData($this->model->table);

        foreach ($fields as $field) {
            $up .= $this->i . "    '{$field->name}' => [" . "\n";
            $up .= $this->i . "        'type'           => '{$field->type}'," . "\n";
            if ($field->max_length) {
                $up .= $this->i . "        'constraint'     => {$field->max_length}," . "\n";
            }
            // if ($field->unsigned) {
            //     $up.= $i."        'unsigned'       => true,".PHP_EOL;
            // }
            if ($field->nullable) {
                $up .= $this->i . "        'null'           => true," . "\n";
            }
            if (null !== $field->default) {
                $up .= $this->i . "        'default'        => '{$field->default}'," . "\n";
            }
            if ($field->primary_key === 1) {
                $up .= $this->i . "        'auto_increment' => true," . "\n";
            }
            $up .= $this->i . '    ],' . "\n";
        }
        #$up .= $this->i . ']);' . "\n";

        $this->up .= $up ;

    }

    protected function parseUpKeys()
    {

        $indexes = $this->model->db->getIndexData($this->model->table);

        $up = '';

        foreach ($indexes as $index) {

            $fieldArray = "['" . implode("', '", $index->fields) . "']";

            if ($index->type === "PRIMARY") {
                $up.= $this->i . "\$this->forge->addKey(" . $fieldArray . ", true);" . "\n";
            } elseif ($index->type === "INDEX") {
                $up.= $this->i . "\$this->forge->addKey(" . $fieldArray . ", false, false, '" . $index->name . "');" . "\n";
            } elseif ($index->type === "UNIQUE") {
                $up.= $this->i . "\$this->forge->addKey(" . $fieldArray . ", false, true, '" . $index->name . "');" . "\n";
            } else {
                $up.= $this->i . "# No Parser for Type >". $index->type ."<" . "\n";
                $up.= $this->i . "# INDEX: " . json_encode($index) . "\n";
            }
        }

        $this->up .= $up . "\n";

    }

    protected function parseAddKeys($indexes)
    {

        $up = '';

        foreach ($indexes as $index) {

            $fields = is_array($index) ? $index['fields'] : $index->fields;
            $name   = is_array($index) ? $index['name'] : $index->name;
            $type   = is_array($index) ? $index['type'] : $index->type;

            $fieldArray = "['" . implode("', '", $fields) . "']";

            if ($type === "PRIMARY") {
                $up.= $this->i . "\$this->forge->addKey(" . $fieldArray . ", true);" . "\n";
            } elseif ($type === "INDEX") {
                $up.= $this->i . "\$this->forge->addKey(" . $fieldArray . ", false, false, '" . $name . "');" . "\n";
            } elseif ($type === "UNIQUE") {
                $up.= $this->i . "\$this->forge->addKey(" . $fieldArray . ", false, true, '" . $name . "');" . "\n";
            } else {
                $up.= $this->i . "# No Parser for Type >". $type ."<" . "\n";
                $up.= $this->i . "# INDEX: " . json_encode($index) . "\n";
            }
        }

        return $up . "\n";
    }

    protected function parseDropKeys($indexes)
    {

        $down = '';

        foreach ($indexes as $index) {
            $name = is_array($index) ? $index['name'] : $index->name;
            $down .= $this->i . '$this->forge->dropKey(\'' . $this->model->table . '\', \'' . $name . '\', false);' . "\n";
        }

        return $down . "\n";

    }

    protected function parseUpForeignkeys()
    {

        $foreignkeys = $this->model->db->getForeignKeyData($this->model->table);

        $up = '';

        foreach ($foreignkeys as $foreignkey) {

            $up .= $this->i . "\$this->forge->addForeignKey('" . $foreignkey->column_name[0] . "', '" . $foreignkey->foreign_table_name . "', '" . $foreignkey->foreign_column_name[0] . "', '" . $foreignkey->on_delete . "', '" . $foreignkey->on_update . "', '" . $foreignkey->constraint_name . "');" . "\n";

        }

        $this->up .= $up . "\n";

    }

    protected function parseUpTable()
    {
        $this->up .= $this->i . "\$this->forge->createTable('" . $this->model->table . "');" . "\n" . "\n";
    }

    protected function parseDownTable()
    {
        $this->down .= $this->i . "\$this->forge->dropTable('" . $this->model->table . "');" . "\n" . "\n";
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
        $this->up .= $this->i . '$this->db->disableForeignKeyChecks();' . "\n" . "\n";
    }

    protected function enableForeignKeyChecks()
    {
        if (! $this->getOption('disableForeignKeyChecks')) {
            return;
        }
        $this->up .= $this->i . '$this->db->enableForeignKeyChecks();' . "\n" . "\n";
    }

    protected function isForced(): bool
    {
        return $this->getOption('force') ? true : false;
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
