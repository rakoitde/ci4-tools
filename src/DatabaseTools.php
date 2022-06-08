<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Rakoitde\Tools;

use Rakoitde\Tools\Config\Tools;

use Rakoitde\Tools\Environment;
use Rakoitde\Tools\Structure;

/**
 * This class describes database tools.
 */
class DatabaseTools
{
    protected $db_dev;

    protected $db_test;

    protected $db_prod;

    protected $config;

    protected $CHARACTER_SET;

    protected Environment $dev;

    protected Environment $test;

    protected Environment $prod;

    protected Environment $from;

    protected Environment $to;

    protected array $tables_to_compare;

    protected array $tables_to_alter;

    protected array $tables_equal;

    /**
     * Compares two environments
     *
     * @param      string  $from_env  The from environment
     * @param      string  $to_env    The To environment
     */
    public function compare(string $from_env = 'dev', string $to_env = 'test', string $tablefilter = "dms*")
    {

        $this->setMaxExecutionTime();

        switch ($from_env) {
            case 'dev':  $this->from = $this->dev;  break;
            case 'test': $this->from = $this->test; break;
            case 'prod': $this->from = $this->prod; break;
        }

        switch ($to_env) {
            case 'dev':  $this->to = $this->dev;  break;
            case 'test': $this->to = $this->test; break;
            case 'prod': $this->to = $this->prod; break;
        }

        if ($this->from->isConnected() && $this->to->isConnected()) {

            // get tablenamen to create or drop
            $this->from->tablesToCreate($this->to);
            $this->from->tablesToDrop($this->to);
            $this->to->tablesToCreate($this->from);
            $this->to->tablesToDrop($this->from);

            // generate commands to create or drop tables
            $this->from->createCommands($this->to);
            $this->from->dropCommands($this->from);
            $this->to->createCommands($this->from);
            $this->to->dropCommands($this->to);

            $this->tables_to_compare = array_intersect($this->from->Tables(), $this->to->Tables());
            #$this->compareTableStructures();
            d($this->from->Constraints());
            d($this->to->Constraints());
            #d(array_diff($this->from->ContraintsCommandsStrings(), $this->to->ContraintsCommandsStrings()));
            #d($this->updateExistingTables());
            $this->determineConstrainsChanges();

        } else {

            d($this->from->error(), $this->to->error());

        }

    }

    /**
     * Sets the maximum execution time.
     *
     * @param      string  $seconds  The seconds
     *
     * @return     self    ( description_of_the_return_value )
     */
    public function setMaxExecutionTime(string $seconds = '300'): self
    {
        ini_set('max_execution_time', $seconds); //300 seconds = 5 minutes
        return $this;
    }
        // list the tables from both database
        #$this->development_tables = $this->db_dev->listTables();
        #$this->test_tables        = $this->db_test->listTables();
        #$this->productive_tables  = $this->db_prod->listTables();

    /**
     * { function_description }
     */
    public function tablesToCreate()
    {

        // list any tables that need to be created or dropped
        $tables_to_create = array_diff($development_tables, $live_tables);
        $tables_to_drop   = array_diff($live_tables, $development_tables);

        /**
         * Create/Drop any tables that are not in the Live database
         */

        // This will become a list of SQL Commands to run on the Live database to bring it up to date
        $sql_commands_to_run = [];

        //$sql_commands_to_run = (is_array($tables_to_create) && !empty($tables_to_create)) ? array_merge($sql_commands_to_run, $this->manage_tables($tables_to_create, 'create')) : array();

        $sql_commands_to_run = array_merge($sql_commands_to_run, $this->manage_tables($tables_to_create, 'create'));

        //$sql_commands_to_run = (is_array($tables_to_drop) && !empty($tables_to_drop)) ? array_merge($sql_commands_to_run, $this->manage_tables($tables_to_drop, 'drop')) : array();
        $sql_commands_to_run = array_merge($sql_commands_to_run, $this->manage_tables($tables_to_drop, 'drop'));

        $tables_to_update = $this->compare_table_structures($development_tables, $live_tables);

        // Before comparing tables, remove any tables from the list that will be created in the $tables_to_create array
        $tables_to_update = array_diff($tables_to_update, $tables_to_create);
        // update tables, add/update/emove columns
        //$sql_commands_to_run = (is_array($tables_to_update) && !empty($tables_to_update)) ? array_merge($sql_commands_to_run, $this->update_existing_tables($tables_to_update)) : '';
        $sql_commands_to_run = array_merge($sql_commands_to_run, $this->update_existing_tables($tables_to_update));

        $sql_commands_to_run = array_merge($sql_commands_to_run, $this->determine_constrains_changes($development_tables));

        if (is_array($sql_commands_to_run) && ! empty($sql_commands_to_run)) {
            echo "<h2>The database is out of Sync!</h2>\n";
            echo "<p>The following SQL commands need to be executed to bring the Live database tables up to date: </p>\n";
            echo "<pre style='padding: 20px; background-color: #FFFAF0;'>\n";

            foreach ($sql_commands_to_run as $sql_command) {
                echo "{$sql_command}\n";
            }
            echo "<pre>\n";
        } else {
            echo "<h2>The database appears to be up to date</h2>\n";
        }
    }

    /**
     * Go through each table, compare their sql structure
     *
     * @param      array  $development_tables
     * @param      array  $live_tables
     *
     * @return     array  ( description_of_the_return_value )
     */
    public function compareTableStructures()
    {

        $this->tables_to_alter = [];
        $this->tables_equal = [];


        foreach ($this->tables_to_compare as $table) {
            // from 
            $from_create_command = $this->from->db->query("SHOW CREATE TABLE `{$table}` -- dev")->getResultArray()[0];
            $from_structure = $from_create_command['Create Table'] ?? $from_create_command['Create View'];

            // from 
            $to_create_command = $this->to->db->query("SHOW CREATE TABLE `{$table}` -- dev")->getResultArray()[0];
            $to_structure = $to_create_command['Create Table'] ?? $to_create_command['Create View'];

            if ($this->countDifferences($from_structure, $to_structure) > 0) {
                $this->tables_to_alter[] = $table;
            } else {
                $this->tables_equal[] = $table;
            }
        }
    }

    /**
     * Count differences in 2 sql statements
     *
     * @param      string  $old
     * @param      string  $new
     *
     * @return     int     $differences
     */
    public function countDifferences($old, $new)
    {
        $differences = 0;
        $old         = trim(preg_replace('/\s+/', '', $old));
        $new         = trim(preg_replace('/\s+/', '', $new));

        if ($old === $new) {
            return $differences;
        }

        $old    = explode(' ', $old);
        $new    = explode(' ', $new);
        $length = max(count($old), count($new));

        for ($i = 0; $i < $length; $i++) {
            if ($old[$i] !== $new[$i]) {
                $differences++;
            }
        }

        return $differences;
    }


    /**
     * Given an array of tables that differ from DB1 to DB2, update DB2
     *
     * @param      array   $tables
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function updateExistingTables()
    {

        $table_structures = [];
        $table_structure_development = [];
        $table_structure_live = [];
        $sql_commands_to_run     = [];

        foreach ($this->tables_to_alter as $table) {

            $structure = new Structure($table);
            $structure->from($this->from->TableFieldData($table));
            $structure->to(  $this->to->TableFieldData($table));
            $structure->compare();

            $table_structures[] = $structure;

            $table_structure_development[$table] = $this->from->TableFieldData($table);
            $table_structure_live[$table]        = $this->to->TableFieldData($table);

        }

        d($table_structures);
        return;

        $this->structurefrom = $table_structure_development;
        $this->structureto = $table_structure_live;

        // add, remove or update any fields in $table_structure_live
        return array_merge($sql_commands_to_run, $this->determineFieldChanges($table_structure_development, $table_structure_live));
    }

    /**
     * Gets the constraints.
     *
     * @param      <type>  $db     The database
     * @param      <type>  $table  The table
     *
     * @return     <type>  The constraints.
     */
    private function __getConstraints($db, $table)
    {
        $shema = $db->getDatabase();

        $sql = "
        SELECT
            c.TABLE_SCHEMA,
            c.CONSTRAINT_NAME,
            c.TABLE_NAME,
            c.COLUMN_NAME,
            c.REFERENCED_TABLE_NAME,
            c.REFERENCED_COLUMN_NAME,
            rc.DELETE_RULE,
            rc.UPDATE_RULE
        FROM
            information_schema.KEY_COLUMN_USAGE c
            LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON c.CONSTRAINT_SCHEMA=rc.CONSTRAINT_SCHEMA and c.TABLE_NAME=rc.TABLE_NAME and c.CONSTRAINT_NAME=rc.CONSTRAINT_NAME
        WHERE
            c.REFERENCED_TABLE_NAME IS NOT null AND
            c.CONSTRAINT_SCHEMA = '{$shema}' AND
            c.TABLE_NAME = '{$table}'
        ORDER BY
            c.CONSTRAINT_SCHEMA,
            c.TABLE_NAME,
            c.CONSTRAINT_NAME";

        $constraints = $db->query($sql);

        return $constraints->getResultArray();
    }

    /**
     * Given an array of tables that differ from DB1 to DB2, update DB2
     *
     * @param      array  $tables
     *
     * @return     array  ( description_of_the_return_value )
     */
    public function determineConstrainsChanges()
    {

        $from_constraints = $this->from->ContraintsCommandsStrings();
        $to_constraints = $this->to->ContraintsCommandsStrings();

        $from_constraints_missing = array_diff($this->from->ContraintsCommandsStrings(), $this->to->ContraintsCommandsStrings());
        $to_constraints_missing = array_diff($this->to->ContraintsCommandsStrings(), $this->from->ContraintsCommandsStrings());

        $from_constraints_drop = array_diff(array_merge($from_constraints_missing, $from_constraints), $to_constraints);
        $to_constraints_drop   = array_diff(array_merge($to_constraints_missing, $to_constraints), $from_constraints);

d($from_constraints, $from_constraints_missing, $to_constraints, $to_constraints_missing, $from_constraints_drop, $to_constraints_drop);

        $tables = $this->from->Tables();
        $sql_commands_to_run          = [];
        $table_constrains_development = [];
        $table_constrains_live        = [];

        if (is_array($tables) && ! empty($tables)) {
            foreach ($tables as $table) {

        //d($table);
                $table_constrains_development[$table] = $this->getConstraints($this->from->db, $table);
                $table_constrains_live[$table]        = $this->getConstraints($this->to->db, $table);

                // constraints exists
                $existing_constraints = [];

                foreach ($table_constrains_live[$table] as $constraint) {
                    $existing_constraints[] = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint['CONSTRAINT_NAME']}` FOREIGN KEY (`{$constraint['COLUMN_NAME']}`) REFERENCES `{$constraint['REFERENCED_TABLE_NAME']}` (`{$constraint['REFERENCED_COLUMN_NAME']}`) ON DELETE {$constraint['DELETE_RULE']} ON UPDATE {$constraint['UPDATE_RULE']};";
                }

                // add constraints
                $new_constraints           = [];
                $sql_commands_to_run_cache = [];

                foreach ($table_constrains_development[$table] as $constraint) {
                    $new_constraint    = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint['CONSTRAINT_NAME']}` FOREIGN KEY (`{$constraint['COLUMN_NAME']}`) REFERENCES `{$constraint['REFERENCED_TABLE_NAME']}` (`{$constraint['REFERENCED_COLUMN_NAME']}`) ON DELETE {$constraint['DELETE_RULE']} ON UPDATE {$constraint['UPDATE_RULE']};";
                    $new_constraints[] = $new_constraint;
                    if (! in_array($new_constraint, $existing_constraints, true)) {
                        //$sql_commands_to_run[] = "ALTER TABLE `$table` DROP FOREIGN KEY `{$constraint['CONSTRAINT_NAME']}`;";
                        $sql_commands_to_run_cache[] = $new_constraint;
                    }
                }

                // delete constraints
                foreach ($table_constrains_live[$table] as $constraint) {
                    $existing_constraint = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint['CONSTRAINT_NAME']}` FOREIGN KEY (`{$constraint['COLUMN_NAME']}`) REFERENCES `{$constraint['REFERENCED_TABLE_NAME']}` (`{$constraint['REFERENCED_COLUMN_NAME']}`) ON DELETE {$constraint['DELETE_RULE']} ON UPDATE {$constraint['UPDATE_RULE']};";
                    $drop_constraint     = "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint['CONSTRAINT_NAME']}`;";

                    if (! in_array($existing_constraint, $new_constraints, true)) {
                        $sql_commands_to_run[] = $drop_constraint;
                    }
                }

                $sql_commands_to_run = array_merge($sql_commands_to_run, $sql_commands_to_run_cache);

                $table_constrains_development[$table] = $this->from->db->getForeignKeyData($table);
                $table_constrains_live[$table]        = $this->to->db->getForeignKeyData($table);

                foreach ($table_constrains_development[$table] as $constraint) {
                    //$sql_commands_to_run[] = "ALTER TABLE `$table` ADD CONSTRAINT `{$constraint->constraint_name}` FOREIGN KEY (`{$constraint->column_name}`) REFERENCES `{$constraint->foreign_table_name}` (`{$constraint->foreign_column_name}`) ON DELETE CASCADE ON UPDATE RESTRICT;";
                }

                foreach ($table_constrains_live[$table] as $constraint) {
                    //$sql_commands_to_run[] = "ALTER TABLE `$table` DROP FOREIGN KEY `{$constraint->constraint_name}`;";
                }
            }
        }

        // add, remove or update any fields in $table_structure_live
        //$sql_commands_to_run = array_merge($sql_commands_to_run, $this->determine_field_changes($table_structure_development, $table_structure_live));

        return $sql_commands_to_run;
    }

    /**
     * Given an array of tables that differ from DB1 to DB2, update DB2
     *
     * @param      array  $tables
     *
     * @return     array  ( description_of_the_return_value )
     */
    public function determine_constrains_changes($tables)
    {
        $sql_commands_to_run          = [];
        $table_constrains_development = [];
        $table_constrains_live        = [];

        if (is_array($tables) && ! empty($tables)) {
            foreach ($tables as $table) {

        //d($table);
                $table_constrains_development[$table] = $this->getConstraints($this->db_dev, $table);
                $table_constrains_live[$table]        = $this->getConstraints($this->db_prod, $table);

                // constraints exists
                $existing_constraints = [];

                foreach ($table_constrains_live[$table] as $constraint) {
                    $existing_constraints[] = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint['CONSTRAINT_NAME']}` FOREIGN KEY (`{$constraint['COLUMN_NAME']}`) REFERENCES `{$constraint['REFERENCED_TABLE_NAME']}` (`{$constraint['REFERENCED_COLUMN_NAME']}`) ON DELETE {$constraint['DELETE_RULE']} ON UPDATE {$constraint['UPDATE_RULE']};";
                }

                // add constraints
                $new_constraints           = [];
                $sql_commands_to_run_cache = [];

                foreach ($table_constrains_development[$table] as $constraint) {
                    $new_constraint    = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint['CONSTRAINT_NAME']}` FOREIGN KEY (`{$constraint['COLUMN_NAME']}`) REFERENCES `{$constraint['REFERENCED_TABLE_NAME']}` (`{$constraint['REFERENCED_COLUMN_NAME']}`) ON DELETE {$constraint['DELETE_RULE']} ON UPDATE {$constraint['UPDATE_RULE']};";
                    $new_constraints[] = $new_constraint;
                    if (! in_array($new_constraint, $existing_constraints, true)) {
                        //$sql_commands_to_run[] = "ALTER TABLE `$table` DROP FOREIGN KEY `{$constraint['CONSTRAINT_NAME']}`;";
                        $sql_commands_to_run_cache[] = $new_constraint;
                    }
                }

                // delete constraints
                foreach ($table_constrains_live[$table] as $constraint) {
                    $existing_constraint = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint['CONSTRAINT_NAME']}` FOREIGN KEY (`{$constraint['COLUMN_NAME']}`) REFERENCES `{$constraint['REFERENCED_TABLE_NAME']}` (`{$constraint['REFERENCED_COLUMN_NAME']}`) ON DELETE {$constraint['DELETE_RULE']} ON UPDATE {$constraint['UPDATE_RULE']};";
                    $drop_constraint     = "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint['CONSTRAINT_NAME']}`;";

                    if (! in_array($existing_constraint, $new_constraints, true)) {
                        $sql_commands_to_run[] = $drop_constraint;
                    }
                }

                $sql_commands_to_run = array_merge($sql_commands_to_run, $sql_commands_to_run_cache);

                $table_constrains_development[$table] = $this->db_dev->getForeignKeyData($table);
                $table_constrains_live[$table]        = $this->db_prod->getForeignKeyData($table);

                foreach ($table_constrains_development[$table] as $constraint) {
                    //$sql_commands_to_run[] = "ALTER TABLE `$table` ADD CONSTRAINT `{$constraint->constraint_name}` FOREIGN KEY (`{$constraint->column_name}`) REFERENCES `{$constraint->foreign_table_name}` (`{$constraint->foreign_column_name}`) ON DELETE CASCADE ON UPDATE RESTRICT;";
                }

                foreach ($table_constrains_live[$table] as $constraint) {
                    //$sql_commands_to_run[] = "ALTER TABLE `$table` DROP FOREIGN KEY `{$constraint->constraint_name}`;";
                }
            }
        }

        // add, remove or update any fields in $table_structure_live
        //$sql_commands_to_run = array_merge($sql_commands_to_run, $this->determine_field_changes($table_structure_development, $table_structure_live));

        return $sql_commands_to_run;
    }

    /**
     * Recursive version of in_array
     *
     * @param      string  $needle
     * @param      array   $haystack
     * @param      bool    $strict
     *
     * @return     bool
     */
    public function in_array_recursive($needle, $haystack, $strict = false)
    {
        foreach ($haystack as $array => $item) {
            $item = $item['Field']; // look in the name field only
            if (($strict ? $item === $needle : $item === $needle) || (is_array($item) && $this->in_array_recursive($needle, $item, $strict))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->CHARACTER_SET = 'utf8 COLLATE utf8_general_ci';
        $this->config        = Config('Tools');

        $this->dev  = (new Environment('dev') )->connect();
        $this->test = (new Environment('test'))->connect();
        $this->prod = (new Environment('prod'))->connect();

    }



    /**
     * Given an array of tables that differ from DB1 to DB2, update DB2
     *
     * @param      array   $tables
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function ____update_existing_tables($tables)
    {
        $sql_commands_to_run         = [];
        $table_structure_development = [];
        $table_structure_live        = [];

        if (is_array($tables) && ! empty($tables)) {
            foreach ($tables as $table) {
                $table_structure_development[$table] = $from->TableFieldData($table);
                $table_structure_live[$table]        = $to->TableFieldData($table);
            }
        }

        // add, remove or update any fields in $table_structure_live
        return array_merge($sql_commands_to_run, $this->determine_field_changes($table_structure_development, $table_structure_live));
    }

    /**
     * Given a database and a table, compile an array of field meta data
     *
     * @param      mixed   $db
     * @param      string  $table
     *
     * @return     array   $fields
     */
    public function ____table_field_data($db, $table)
    {
        //$conn = mysqli_connect($database["hostname"], $database["username"], $database["password"]);

        //mysql_select_db($database["database"]);

        $result = $db->query("SHOW COLUMNS FROM `{$table}`");

        return $result->getResultArray();
    }

    /**
     * Given to arrays of table fields, add/edit/remove fields
     *
     * @param      array  $source_field_structures       The source field structures
     * @param      array  $destination_field_structures  The destination field structures
     *
     * @return     array  ( description_of_the_return_value )
     */
    public function ___determineFieldChanges(array $source_field_structures, array $destination_field_structures)
    {
        $sql_commands_to_run = [];

        /**
         * loop through the source (usually development) database
         */
        foreach ($source_field_structures as $table => $fields) {
            foreach ($fields as $field) {
                if ($this->in_array_recursive($field['Field'], $destination_field_structures[$table])) {
                    $modify_field = '';
                    // Check for required modifications
                    for ($n = 0; $n < count($fields); $n++) {


                        if (isset($fields[$n], $destination_field_structures[$table][$n]) && ($fields[$n]['Field'] === $destination_field_structures[$table][$n]['Field'])) {
                            $differences = array_diff($fields[$n], $destination_field_structures[$table][$n]);
                            if (is_array($differences) && ! empty($differences)) {
                                // ALTER TABLE `bugs` MODIFY COLUMN `site_name`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `type`;
                                // ALTER TABLE `bugs` MODIFY COLUMN `message`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `site_name`;
                                $modify_field = "ALTER TABLE {$table} CHANGE `" . $fields[$n]['Field'] . '` `' . $fields[$n]['Field'] . '` ' . $fields[$n]['Type'];
                                if (substr($fields[$n]['Type'], 0, 7) === 'varchar') {
                                    $modify_field .= ' CHARACTER SET ' . $this->CHARACTER_SET;
                                }
                                $modify_field .= (isset($fields[$n]['Default']) && $fields[$n]['Default'] !== '') ? ' DEFAULT \'' . $fields[$n]['Default'] . '\'' : '';
                                $modify_field .= (isset($fields[$n]['Null']) && $fields[$n]['Null'] === 'YES') ? ' NULL' : ' NOT NULL';
                                $modify_field .= (isset($fields[$n]['Extra']) && $fields[$n]['Extra'] !== '') ? ' ' . $fields[$n]['Extra'] : '';
                                $modify_field .= (isset($previous_field) && $previous_field !== '') ? ' AFTER ' . $previous_field : '';
                                $modify_field .= ';';
                            }
                            $previous_field = $fields[$n]['Field'];
                        }

                        if ($modify_field !== '' && ! in_array($modify_field, $sql_commands_to_run, true)) {
                            $sql_commands_to_run[] = $modify_field;
                        }
                    }
                } else {
                    // Add
                    $add_field = "ALTER TABLE {$table} ADD `" . $field['Field'] . '` ' . $field['Type'];

                    if (substr($field['Type'], 0, 7) === 'varchar') {
                        $add_field .= ' CHARACTER SET ' . $this->CHARACTER_SET;
                    }

                    $add_field .= (isset($field['Null']) && $field['Null'] === 'YES') ? ' Null' : ' Not Null';
                    if (isset($field['Default'])) {
                        $add_field .= ' DEFAULT ' . $field['Default'];
                    }
                    $add_field .= (isset($field['Extra']) && $field['Extra'] !== '') ? ' ' . $field['Extra'] : '';
                    $add_field .= ';';
                    $sql_commands_to_run[] = $add_field;
                }
            }
        }

        return $sql_commands_to_run;
    }

    /**
     * Given to arrays of table fields, add/edit/remove fields
     *
     * @param      array  $source_field_structures       The source field structures
     * @param      array  $destination_field_structures  The destination field structures
     *
     * @return     array  ( description_of_the_return_value )
     */
    public function _____determineFieldChanges(array $source_field_structures, array $destination_field_structures)
    {
        $sql_commands_to_run = [];

        /**
         * loop through the source (usually development) database
         */
        foreach ($source_field_structures as $table => $fields) {
            foreach ($fields as $field) {
                if ($this->in_array_recursive($field['Field'], $destination_field_structures[$table])) {
                    $modify_field = '';
                    // Check for required modifications
                    for ($n = 0; $n < count($fields); $n++) {
                        if (isset($fields[$n], $destination_field_structures[$table][$n]) && ($fields[$n]['Field'] === $destination_field_structures[$table][$n]['Field'])) {
                            $differences = array_diff($fields[$n], $destination_field_structures[$table][$n]);
                            if (is_array($differences) && ! empty($differences)) {
                                // ALTER TABLE `bugs` MODIFY COLUMN `site_name`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `type`;
                                // ALTER TABLE `bugs` MODIFY COLUMN `message`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `site_name`;
                                $modify_field = "ALTER TABLE {$table} CHANGE `" . $fields[$n]['Field'] . '` `' . $fields[$n]['Field'] . '` ' . $fields[$n]['Type'];
                                if (substr($fields[$n]['Type'], 0, 7) === 'varchar') {
                                    $modify_field .= ' CHARACTER SET ' . $this->CHARACTER_SET;
                                }
                                $modify_field .= (isset($fields[$n]['Default']) && $fields[$n]['Default'] !== '') ? ' DEFAULT \'' . $fields[$n]['Default'] . '\'' : '';
                                $modify_field .= (isset($fields[$n]['Null']) && $fields[$n]['Null'] === 'YES') ? ' NULL' : ' NOT NULL';
                                $modify_field .= (isset($fields[$n]['Extra']) && $fields[$n]['Extra'] !== '') ? ' ' . $fields[$n]['Extra'] : '';
                                $modify_field .= (isset($previous_field) && $previous_field !== '') ? ' AFTER ' . $previous_field : '';
                                $modify_field .= ';';
                            }
                            $previous_field = $fields[$n]['Field'];
                        }

                        if ($modify_field !== '' && ! in_array($modify_field, $sql_commands_to_run, true)) {
                            $sql_commands_to_run[] = $modify_field;
                        }
                    }
                } else {
                    // Add
                    $add_field = "ALTER TABLE {$table} ADD `" . $field['Field'] . '` ' . $field['Type'];

                    if (substr($field['Type'], 0, 7) === 'varchar') {
                        $add_field .= ' CHARACTER SET ' . $this->CHARACTER_SET;
                    }

                    $add_field .= (isset($field['Null']) && $field['Null'] === 'YES') ? ' Null' : ' Not Null';
                    if (isset($field['Default'])) {
                        $add_field .= ' DEFAULT ' . $field['Default'];
                    }
                    $add_field .= (isset($field['Extra']) && $field['Extra'] !== '') ? ' ' . $field['Extra'] : '';
                    $add_field .= ';';
                    $sql_commands_to_run[] = $add_field;
                }
            }
        }

        return $sql_commands_to_run;
    }

    /**
     * Given to arrays of table fields, add/edit/remove fields
     *
     * @param      array  $source_field_structures       The source field structures
     * @param      array  $destination_field_structures  The destination field structures
     *
     * @return     array  ( description_of_the_return_value )
     */
    public function ____determine_field_changes(array $source_field_structures, array $destination_field_structures)
    {
        $sql_commands_to_run = [];

        /**
         * loop through the source (usually development) database
         */
        foreach ($source_field_structures as $table => $fields) {
            foreach ($fields as $field) {
                if ($this->in_array_recursive($field['Field'], $destination_field_structures[$table])) {
                    $modify_field = '';
                    // Check for required modifications
                    for ($n = 0; $n < count($fields); $n++) {
                        if (isset($fields[$n], $destination_field_structures[$table][$n]) && ($fields[$n]['Field'] === $destination_field_structures[$table][$n]['Field'])) {
                            $differences = array_diff($fields[$n], $destination_field_structures[$table][$n]);
                            if (is_array($differences) && ! empty($differences)) {
                                // ALTER TABLE `bugs` MODIFY COLUMN `site_name`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `type`;
                                // ALTER TABLE `bugs` MODIFY COLUMN `message`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `site_name`;
                                $modify_field = "ALTER TABLE {$table} CHANGE `" . $fields[$n]['Field'] . '` `' . $fields[$n]['Field'] . '` ' . $fields[$n]['Type'];
                                if (substr($fields[$n]['Type'], 0, 7) === 'varchar') {
                                    $modify_field .= ' CHARACTER SET ' . $this->CHARACTER_SET;
                                }
                                $modify_field .= (isset($fields[$n]['Default']) && $fields[$n]['Default'] !== '') ? ' DEFAULT \'' . $fields[$n]['Default'] . '\'' : '';
                                $modify_field .= (isset($fields[$n]['Null']) && $fields[$n]['Null'] === 'YES') ? ' NULL' : ' NOT NULL';
                                $modify_field .= (isset($fields[$n]['Extra']) && $fields[$n]['Extra'] !== '') ? ' ' . $fields[$n]['Extra'] : '';
                                $modify_field .= (isset($previous_field) && $previous_field !== '') ? ' AFTER ' . $previous_field : '';
                                $modify_field .= ';';
                            }
                            $previous_field = $fields[$n]['Field'];
                        }

                        if ($modify_field !== '' && ! in_array($modify_field, $sql_commands_to_run, true)) {
                            $sql_commands_to_run[] = $modify_field;
                        }
                    }
                } else {
                    // Add
                    $add_field = "ALTER TABLE {$table} ADD `" . $field['Field'] . '` ' . $field['Type'];

                    if (substr($field['Type'], 0, 7) === 'varchar') {
                        $add_field .= ' CHARACTER SET ' . $this->CHARACTER_SET;
                    }

                    $add_field .= (isset($field['Null']) && $field['Null'] === 'YES') ? ' Null' : ' Not Null';
                    if (isset($field['Default'])) {
                        $add_field .= ' DEFAULT ' . $field['Default'];
                    }
                    $add_field .= (isset($field['Extra']) && $field['Extra'] !== '') ? ' ' . $field['Extra'] : '';
                    $add_field .= ';';
                    $sql_commands_to_run[] = $add_field;
                }
            }
        }

        return $sql_commands_to_run;
    }

}
