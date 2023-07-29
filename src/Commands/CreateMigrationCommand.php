<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CreateMigrationCommand extends BaseCommand
{
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
    protected $name = 'create:migration';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new migration file from table informations';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'create:migration [table] [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'table' => 'Generates a migration file for this table'
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--debug' => 'shows debug informations'
    ];

    protected $tableName;

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {

        $this->tableName = $params[0];

        $migrationFilePath = $this->generateMigrationFile();

        if ($migrationFilePath) {
            echo "Migration file created successfully: " . $migrationFilePath; } else {
            echo "Failed to create migration file."; }

    }

    private function getFields()
    {
        $db = db_connect();

        if ($db->tableExists($this->tableName)) {
            $fields = $db->getFieldData($this->tableName);

            #print_r($fields);

            return $fields;
        }

        return [];
    }

    private function getKeys()
    {
        $db = db_connect();

        if ($db->tableExists($this->tableName)) {
            $keys = $db->getIndexData($this->tableName);

            #print_r($keys);

            return $keys;
        }

        return [];
    }

    private function getForeignKeys()
    {
        $db = db_connect();

        if ($db->tableExists($this->tableName)) {
            $keys = $db->getForeignKeyData($this->tableName);

            print_r($keys);

            return $keys;
        }

        return [];
    }

    /**
     * Delete all idexes for given table
     *
     * @param      string  $table  The tablename
     */
    private function deleteTableIndex(string $table) {
        CLI::write("Delete index entries", "yellow");
        $db = \Config\Database::connect();
        $builder = $db->table("search_index");
        $builder->where('table', $table);
        $builder->delete();
    }

    /**
     * Creates an index for given table with given command
     *
     * @param      string  $table  The table
     * @param      string  $sql    The sql
     */
    private function createIndex(string $table, string $sql) {
        CLI::write("Create index entries", "yellow");
        $commands = explode(";", $sql);
        foreach ($commands as $command) {
            if ($command!=="") {
                $this->runCommand($command);
            }
        }
    }

    /**
     * Run the insert command
     *
     * @param      string  $command  The insert command
     */
    private function runCommand($command) {
        CLI::print("Run command: ", "yellow");
        if (CLI::getOption("debug")) {
            CLI::print(trim($command), "white");
            CLI::print(" ");
        }
        $db = \Config\Database::connect();
        if ($db->simpleQuery($command)) {
            CLI::print("Success!", "green");
        } else {
            CLI::print("Query failed!", "red");
            CLI::print($db->error(), "red");
        }
        CLI::print(PHP_EOL);
    }

    public function generateMigrationFile()
    {
        $migrationName = 'Create_' . ucfirst($this->tableName) . '_table';
        $timestamp = date("Y-m-d-His");
        $timestamp = '2023-07-29-085947';
        $filePath = APPPATH . 'Database/Migrations/' . $timestamp . '_' . $migrationName . '.php';

        $migrationContent = $this->getMigrationContent();

        if (file_put_contents($filePath, $migrationContent) !== false) {
            return $filePath;
        }

        return false;
    }

    protected function getMigrationContent()
    {


        $migrationTemplate = <<<EOT
<?php namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class {MigrationName} extends Migration
{
    public function up()
    {
{disableForeignKeyChecks}
        \$this->forge->addField([
{FieldsContent}
        ]);
{KeysContent}
{ForeignKeysContent}
        \$this->forge->createTable('{TableName}');
{enableForeignKeyChecks}
    }

    public function down()
    {
        \$this->forge->dropTable('{TableName}');
    }
}
EOT;

        $migrationTemplate = str_replace('{MigrationName}', 'Create' . ucfirst($this->tableName) . 'Table', $migrationTemplate);
        $migrationTemplate = str_replace('{FieldsContent}', $this->getFieldContent(), $migrationTemplate);
        $migrationTemplate = str_replace('{TableName}', $this->tableName, $migrationTemplate);
        $migrationTemplate = str_replace('{KeysContent}', $this->getKeysContent(), $migrationTemplate);
        $migrationTemplate = str_replace('{ForeignKeysContent}', $this->getForeignKeysContent(), $migrationTemplate);
        if (count($this->getForeignKeys()) > 0) {
            $migrationTemplate = str_replace('{disableForeignKeyChecks}', "\t\t\$this->db->disableForeignKeyChecks();\n", $migrationTemplate);
            $migrationTemplate = str_replace('{enableForeignKeyChecks}', "\n\t\t\$this->db->enableForeignKeyChecks();", $migrationTemplate);
        }

        return $migrationTemplate;
    }

    protected function getFieldContent()
    {
        $fields = $this->getFields();

        $fieldsContent = '';
        foreach ($fields as $field) {
            $fieldsContent .= "\t\t\t'" . $field->name . "' => [";
            $fieldsContent .= "'type' => '" . $field->type . "',";
            $fieldsContent .= isset($field->max_length) ? " 'constraint' => " . $field->max_length . "," : "";
            $fieldsContent .= ($field->nullable===true) ? " 'null' => true," : "";
            $fieldsContent .= isset($field->default)    ? " 'default' => '". $field->default ."'," : "";
            $fieldsContent .= ($field->primary_key==1) ? " 'auto_increment' => true," : "";
            $fieldsContent .= "]," . PHP_EOL;
        }

        return $fieldsContent;
    }

    protected function getKeysContent()
    {
        $keys = $this->getKeys();

        $keysContent = '';

        foreach ($keys as $name => $key) {

            switch ($key->type) {
                case 'PRIMARY':
                    foreach ($key->fields as $field) {
                        $keysContent .= "\t\t\$this->forge->addPrimaryKey('". $field ."', '" . $key->name . "');" . PHP_EOL;
                    }
                    break;
                case 'UNIQUE':
                        $keysContent .= "\t\t\$this->forge->addUniqueKey(['". implode("', '", $key->fields) ."'], '" . $key->name . "');" . PHP_EOL;
                    break;
                case 'INDEX':
                    foreach ($key->fields as $field) {
                        $keysContent .= "\t\t\$this->forge->addKey('". $field ."', false, false,'" . $key->name . "');" . PHP_EOL;
                    }
                    break;
                default:
                    // code...
                    break;
            }

        }

        return $keysContent;
    }

    protected function getForeignKeysContent()
    {
        $keys = $this->getForeignKeys();

        $keysContent = '';

        foreach ($keys as $key) {

            $keysContent .= "\t\t\$this->forge->addForeignKey(";
            $keysContent .= "['". implode("', '", $key->column_name) ."'], ";
            $keysContent .= "'" . $key->foreign_table_name . "', ";
            $keysContent .= "['". implode("', '", $key->foreign_column_name) ."'], ";
            $keysContent .= "'" . $key->on_delete . "', ";
            $keysContent .= "'" . $key->on_update . "', ";
            $keysContent .= "'" . $key->constraint_name . "', ";
            $keysContent .= ");" . PHP_EOL;

        }

        return $keysContent;
    }
}

