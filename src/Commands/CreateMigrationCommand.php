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

    /**
     * Returns the field data from table
     *
     */
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

    /**
     * Returns the key/index data from table
     *
     */
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

    /**
     * Returns the foreign key data from table
     *
     */
    private function getForeignKeys()
    {
        $db = db_connect();

        if ($db->tableExists($this->tableName)) {
            $keys = $db->getForeignKeyData($this->tableName);

            #print_r($keys);

            return $keys;
        }

        return [];
    }

    /**
     * Generates the migration file and return the path on success
     *
     */
    public function generateMigrationFile()
    {
        $migrationName = 'Create_' . ucfirst($this->tableName) . '_table';
        $timestamp = date("Y-m-d-His");
        $filePath = APPPATH . 'Database/Migrations/' . $timestamp . '_' . $migrationName . '.php';

        $migrationContent = $this->getMigrationContent();

        if (file_put_contents($filePath, $migrationContent) !== false) {
            return $filePath;
        }

        return false;
    }

    /**
     * Return the generated migration file content
     *
     */
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
        } else {
            $migrationTemplate = str_replace('{disableForeignKeyChecks}', "", $migrationTemplate);
            $migrationTemplate = str_replace('{enableForeignKeyChecks}', "", $migrationTemplate);
        }

        return $migrationTemplate;
    }

    /**
     * Returns the generated fields content
     *
     */
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

    /**
     * Returns the generated keys content
     *
     */
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

    /**
     * Returns the generated foreign keys content
     *
     */
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

