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

use Throwable;

/**
 * This class describes an environment.
 */
class Environment
{
    public string $environment;
    protected $db;

    /**
     * Database Tools config
     */
    protected $config;

    public bool $isconnected = false;
    protected string $db_version;
    protected $error;
    public string $db_group;

    /**
     * Tables in scope
     */
    public array $tables;

    public array $tables_in_scope;
    public array $tables_to_create;
    public array $tables_to_drop;
    public array $commands;
    public array $constraints;
    public array $constraintcommands;

    /**
     * Connect database of selected environment
     *
     * @return self ( description_of_the_return_value )
     */
    public function connect(): self
    {
        try {
            $this->db          = \Config\Database::connect($this->db_group);
            $this->db_version  = $this->db->getVersion();
            $this->isconnected = true;
        } catch (Throwable $e) {
            $this->isconnected = false;
            $this->error       = $e;
        }

        return $this;
    }

    public function db()
    {
        return $this->db;
    }

    /**
     * Determines if connected.
     *
     * @return bool True if connected, False otherwise.
     */
    public function isConnected(): bool
    {
        return $this->isconnected;
    }

    public function setTablesInScope($tables_in_scope)
    {
        $this->tables_in_scope = is_string($tables_in_scope) ? [$tables_in_scope] : $tables_in_scope;
    }

    /**
     * Return connection error
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * Return Tables array
     *
     * @return array ( description_of_the_return_value )
     */
    public function tables()
    {
        if (! $this->isconnected) {
            return [];
        }
        if (! isset($this->tables)) {
            $this->tables = isset($this->tables_in_scope) ? array_intersect($this->tables_in_scope, $this->db->listTables()) : $this->db->listTables();
        }

        return $this->tables;
    }

    public function tablesToCreate($envtables)
    {
        if (is_string($envtables)) {
            $envtables = [$envtables];
        }

        if (! isset($this->tables_to_create)) {
            $this->tables_to_create = array_diff($envtables, $this->tables());
        }

        return $this->tables_to_create;
    }

    public function tablesToDrop($envtables)
    {
        if (is_string($envtables)) {
            $envtables = [$envtables];
        }

        if (! isset($this->tables_to_drop)) {
            $this->tables_to_drop = array_diff($this->tables(), $envtables);
        }

        return $this->tables_to_create;
    }

    /**
     * Create Commands
     *
     * @return array $sql_commands_to_run
     */
    public function createCommands(Environment $env)
    {
        foreach ($env->tables_to_create as $table) {
            $command          = new Command();
            $command->action  = 'create';
            $command->dbgroup = $this->db->dbgroup;
            $command->table   = $this->db->table;
            $command->query   = "SHOW CREATE TABLE `{$table}` -- create tables";
            $command->result  = $this->db->query($command->query)->getResultArray()[0];
            $command->type    = isset($command->result['Create Table']) ? 'table' : (isset($command->result['Create View']) ? 'view' : 'unknown');
            $command->command = ($command->result['Create Table'] ?? $command->result['Create View']) . ';';

            $env->commands[] = $command;
        }

        return $env->commands;
    }

    /**
     * Manage tables, create or drop them
     *
     * @return array $sql_commands_to_run
     */
    public function dropCommands(Environment $env)
    {
        foreach ($env->tables_to_drop as $table) {
            $command          = new Command();
            $command->action  = 'drop';
            $command->dbgroup = $this->db->dbgroup;
            $command->table   = $this->db->table;
            $command->query   = '';
            $command->result  = '';
            $command->type    = 'table';
            $command->command = "DROP TABLE {$table};";

            $this->commands[] = $command;
        }

        return $this->commands;
    }

    /**
     * Given a database and a table, compile an array of field meta data
     *
     * @param string $table
     *
     * @return array $fields
     */
    public function TableFieldData($table)
    {
        $result = $this->db->query("SHOW COLUMNS FROM `{$table}`");

        return $result->getResultArray();
    }

    /**
     * Gets the constraints.
     *
     * @return array The constraints.
     */
    public function Constraints()
    {
        $tables = implode("', '", $this->tables());

        if (isset($this->constraints)) {
            return $this->constraints;
        }

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
            c.TABLE_NAME in ('{$tables}')
        ORDER BY
            -- c.CONSTRAINT_SCHEMA,
            c.TABLE_NAME,
            c.CONSTRAINT_NAME";

        $constraints = $this->db->query($sql)->getResultArray();

        $this->constraints = [];

        foreach ($constraints as $constraint) {
            $this->constraints[$constraint['CONSTRAINT_NAME']] = new Constraint($constraint);
        }

        return $this->constraints;
    }

    public function ContraintsCommandsStrings()
    {
        if (isset($this->constraintcommands)) {
            return $this->constraintcommands;
        }

        $this->constraintcommands = [];

        foreach ($this->constraints as $constraint) {
            $this->constraintcommands[$constraint->name] = (string) $constraint;
        }

        return $this->constraintcommands;
    }

    /**
     * Constructs a new instance.
     *
     * @param string $environment The environment dev, test or prod
     */
    public function __construct(string $environment = 'dev')
    {
        $environment = in_array($environment, ['dev', 'test', 'prod'], true) ? $environment : 'test';

        $this->config = Config('Tools');

        switch ($environment) {
            case 'dev': $this->db_group = $this->config->db_group_dev;
                break;

            case 'test': $this->db_group = $this->config->db_group_test;
                break;

            case 'prod': $this->db_group = $this->config->db_group_prod;
                break;
        }

        $this->environment = $environment;
    }
}
