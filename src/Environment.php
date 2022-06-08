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

use Rakoitde\Tools\Command;

/**
 * This class describes an environment.
 */
class Environment
{

    protected string $environment;

    public $db;

    protected bool $isconnected = false;

    protected string $db_version;

    protected $error = null;

    protected string $db_group;

    protected array $tables_to_create;

    protected array $tables_to_drop;

    protected array $commands;

    protected array $constraints;

    protected array $constraintcommands;


    /**
     * Connect database of selected environment
     *
     * @return     self  ( description_of_the_return_value )
     */
    public function connect(): self
    {

        try {
            $this->db = \Config\Database::connect($this->db_group);
            $this->db_version = $this->db->getVersion();
            $this->isconnected = true;
        } catch (\Throwable $e) {
            $this->isconnected = false;
            $this->error = $e;
        }

        return $this;
    }

    /**
     * Determines if connected.
     *
     * @return     bool  True if connected, False otherwise.
     */
    public function isConnected(): bool
    {
        return $this->isconnected;
    }

    /**
     * Return connection error
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * Return Tables array
     *
     * @return     array  ( description_of_the_return_value )
     */
    public function Tables()
    {
        if (!$this->isconnected) { return []; }
        if (!isset($this->tables)) {
            $this->tables = $this->db->listTables();
        }
        return $this->tables;
    }

    public function tablesToCreate(Environment $env)
    {
        if (!isset($this->tables_to_create)) {  
            $this->tables_to_create = array_diff($env->tables(), $this->tables());
        }

        return $this->tables_to_create;
    }

    public function tablesToDrop(Environment $env)
    {
        if (!isset($this->tables_to_drop)) {  
            $this->tables_to_drop = array_diff($this->tables(), $env->tables());
        }

        return $this->tables_to_create;
    }

    /**
     * Create Commands
     *
     * @param      array   $tables
     * @param      string  $action
     *
     * @return     array   $sql_commands_to_run
     */
    public function createCommands(Environment $env)
    {

        foreach ($env->tables_to_create as $table) {
            
            $command = new Command();
            $command->action  = 'create';
            $command->dbgroup = $this->db->dbgroup;
            $command->table   = $this->db->table;
            $command->query   = "SHOW CREATE TABLE `{$table}` -- create tables";
            $command->result  = $this->db->query($command->query)->getResultArray()[0];
            $command->type    = isset($command->result['Create Table']) ? 'table' : (isset($command->result['Create View']) ? 'view' : 'unknown');
            $command->command = ($command->result['Create Table'] ?? $command->result['Create View']) . ';';

            $env->commands[] = $command;

        }

    }

    /**
     * Manage tables, create or drop them
     *
     * @param      array   $tables
     * @param      string  $action
     *
     * @return     array   $sql_commands_to_run
     */
    public function dropCommands(Environment $env)
    {

        foreach ($env->tables_to_drop as $table) {

            $command = new Command();
            $command->action  = 'drop';
            $command->dbgroup = $this->db->dbgroup;
            $command->table   = $this->db->table;
            $command->query   = "";
            $command->result  = "";
            $command->type    = "table";
            $command->command = "DROP TABLE {$table};";

            $this->commands[] = $command;

        }
        
    }

    /**
     * Given a database and a table, compile an array of field meta data
     *
     * @param      mixed   $db
     * @param      string  $table
     *
     * @return     array   $fields
     */
    public function TableFieldData($table)
    {

        $result = $this->db->query("SHOW COLUMNS FROM `{$table}`");

        return $result->getResultArray();
    }

    /**
     * Gets the constraints.
     *
     * @param      <type>  $db     The database
     * @param      <type>  $table  The table
     *
     * @return     <type>  The constraints.
     */
    public function Constraints($table = '%')
    {

        if (isset($this->constraints)) { return $this->constraints; }

        $shema = $this->db->getDatabase();

        $sql = "
        SELECT
            -- c.TABLE_SCHEMA,
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
            c.TABLE_NAME like '{$table}'
        ORDER BY
            -- c.CONSTRAINT_SCHEMA,
            c.TABLE_NAME,
            c.CONSTRAINT_NAME";

        $this->constraints = $this->db->query($sql)->getResultArray();

        return $this->constraints;

    }

    public function ContraintsCommandsStrings()
    {

        if (isset($this->constraintcommands)) { return $this->constraintcommands; } 

        $this->constraintcommands = [];

        foreach ($this->constraints as $constraint) {
            $this->constraintcommands[] = "ALTER TABLE `{$constraint['TABLE_NAME']}` ADD CONSTRAINT `{$constraint['CONSTRAINT_NAME']}` FOREIGN KEY (`{$constraint['COLUMN_NAME']}`) REFERENCES `{$constraint['REFERENCED_TABLE_NAME']}` (`{$constraint['REFERENCED_COLUMN_NAME']}`) ON DELETE {$constraint['DELETE_RULE']} ON UPDATE {$constraint['UPDATE_RULE']};";
        }

        return $this->constraintcommands;
    }

    /**
     * Constructs a new instance.
     *
     * @param      string  $environment  The environment dev, test or prod
     */
    public function __construct(string $environment = 'dev')
    {

        $environment = in_array($environment, ['dev','test','prod']) ? $environment : 'test';

        $this->config = Config("Tools");

        switch ($environment) {
            case 'dev' : $this->db_group = $this->config->db_group_dev;  break;
            case 'test': $this->db_group = $this->config->db_group_test; break;
            case 'prod': $this->db_group = $this->config->db_group_prod; break;        
        }

        $this->environment = $environment;
    }

}