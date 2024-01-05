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

/**
 * This class describes database tools.
 */
class DatabaseTools
{
    /**
     * From environment dev, test or prod
     */
    public string $from_env;

    /**
     * To environment dev, test or prod
     */
    public string $to_env;

    /**
     * Database Tools config
     */
    protected $config;

    /**
     * { item_description }
     */
    public $character_set;

    /**
     * Development DB
     */
    protected Environment $dev;

    /**
     * Test DB
     */
    protected Environment $test;

    /**
     * Production DB
     */
    protected Environment $prod;

    /**
     * From DB
     */
    public Environment $from;

    /**
     * To DB
     */
    public Environment $to;

    /**
     * Tables in scope
     */
    public array $tables_in_scope;

    public array $tables_to_compare;
    public array $tables_to_alter;
    public array $tables_equal;
    public $tables;

    /**
     * Sets the environment.
     */
    public function setEnvironment()
    {
        $this->from_env = $this->config->currentenvironment;

        $nextEnv = false;

        foreach ($this->config->environments as $key) {
            $nextEnv = next($this->config->environments);
            if ($key === $this->from_env) {
                break;
            }
        }
        $this->to_env = $nextEnv;

        $this->dev  = (new Environment('dev'))->connect();
        $this->test = (new Environment('test'))->connect();
        $this->prod = (new Environment('prod'))->connect();

        switch ($this->from_env) {
            case 'dev':  $this->from = $this->dev;
                break;

            case 'test': $this->from = $this->test;
                break;

            case 'prod': $this->from = $this->prod;
                break;
        }

        switch ($this->to_env) {
            case 'dev':  $this->to = $this->dev;
                break;

            case 'test': $this->to = $this->test;
                break;

            case 'prod': $this->to = $this->prod;
                break;
        }
    }

    /**
     * Gets the environment.
     *
     * @return array The environment.
     */
    public function getEnvironment(): array
    {
        return [
            'from' => [
                'env'         => $this->from_env,
                'isConnected' => $this->from->isConnected(),
                'hostname'    => $this->from->db()->hostname,
                'port'        => $this->from->db()->port,
                'username'    => $this->from->db()->username,
                'database'    => $this->from->db()->database,
                'charset'     => $this->from->db()->charset,
                'collation'   => $this->from->db()->DBCollat,
            ],
            'to' => [
                'env'         => $this->to_env,
                'isConnected' => $this->to->isConnected(),
                'hostname'    => $this->to->db()->hostname,
                'port'        => $this->to->db()->port,
                'username'    => $this->to->db()->username,
                'database'    => $this->to->db()->database,
                'charset'     => $this->to->db()->charset,
                'collation'   => $this->to->db()->DBCollat,
            ],
        ];
    }

    /**
     * Tables in both databases to compare
     *
     * @return array ( description_of_the_return_value )
     */
    public function getTablesToCompare(): array
    {
        $tables_from = $this->hasTablesInScope() ? array_intersect($this->getTablesInScope(), $this->to->tables()) : $this->to->tables();
        $tables_to   = $this->hasTablesInScope() ? array_intersect($this->getTablesInScope(), $this->from->tables()) : $this->from->tables();

        if (! isset($this->tables_to_compare)) {
            $this->tables_to_compare = array_intersect($tables_from, $tables_to);
        }

        return $this->tables_to_compare;
    }

    public function setTablesInScope($tables_in_scope)
    {
        $this->tables_in_scope = is_string($tables_in_scope) ? [$tables_in_scope] : $tables_in_scope;
        $this->from->setTablesInScope($this->tables_in_scope);
        $this->to->setTablesInScope($this->tables_in_scope);
    }

    public function getTablesInScope()
    {
        return $this->tables_in_scope;
    }

    public function hasTablesInScope()
    {
        return count($this->tables_in_scope) > 0;
    }

    /**
     * Compares two environments
     *
     * @param mixed $tables
     */
    public function compare($tables)
    {
        $this->setTablesInScope($tables);

        $this->setMaxExecutionTime();

        if ($this->from->isConnected() && $this->to->isConnected()) {
            $tables_from = $this->hasTablesInScope() ? $this->getTablesInScope() : $this->to->tables();
            $tables_to   = $this->hasTablesInScope() ? $this->getTablesInScope() : $this->from->tables();

            // get tablenamen to create or drop
            $this->from->tablesToCreate($tables_from);
            $this->from->tablesToDrop($tables_from);
            $this->to->tablesToCreate($tables_to);
            $this->to->tablesToDrop($tables_to);

            // generate commands to create or drop tables
            $this->from->createCommands($this->to);
            $this->from->dropCommands($this->from);
            $this->to->createCommands($this->from);
            $this->to->dropCommands($this->to);

            // $this->tables_to_compare = array_intersect($this->from->tables(), $this->to->tables());
            $this->compareTableStructures();
            $this->from->Constraints();
            $this->to->Constraints();
            // d(array_diff($this->from->ContraintsCommandsStrings(), $this->to->ContraintsCommandsStrings()));
            $this->updateExistingtables();
            $this->determineConstrainsChanges();
        } else {
            d($this->from->error(), $this->to->error());
        }

        return $this;
    }

    /**
     * Sets the maximum execution time.
     *
     * @param string $seconds The seconds
     *
     * @return self ( description_of_the_return_value )
     */
    public function setMaxExecutionTime(string $seconds = '300'): self
    {
        ini_set('max_execution_time', $seconds); // 300 seconds = 5 minutes

        return $this;
    }

    /**
     * Go through each table, compare their sql structure
     */
    public function compareTableStructures()
    {
        $this->tables_to_alter = [];
        $this->tables_equal    = [];

        foreach ($this->getTablesToCompare() as $table) {
            // from
            $from_create_command = $this->from->db()->query("SHOW CREATE TABLE `{$table}` -- dev")->getResultArray()[0];
            $from_structure      = $from_create_command['Create Table'] ?? $from_create_command['Create View'];

            // from
            $to_create_command = $this->to->db()->query("SHOW CREATE TABLE `{$table}` -- dev")->getResultArray()[0];
            $to_structure      = $to_create_command['Create Table'] ?? $to_create_command['Create View'];

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
     * @param string $old
     * @param string $new
     *
     * @return int $differences
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
     */
    public function updateExistingtables()
    {
        foreach ($this->tables_to_alter as $table) {
            $structure = new Structure($table);
            $structure->from($this->from->TableFieldData($table));
            $structure->to($this->to->TableFieldData($table));
            $structure->compare();

            $this->tables[$table] = $structure;
        }
    }

    /**
     * Given an array of tables that differ from DB1 to DB2, update DB2
     */
    public function determineConstrainsChanges()
    {
        $from_constraints = $this->from->ContraintsCommandsStrings();
        $to_constraints   = $this->to->ContraintsCommandsStrings();

        $to_constraints_missing_keys = array_diff(array_keys($this->from->ContraintsCommandsStrings()), array_keys($this->to->ContraintsCommandsStrings()));
        $to_constraints_missing      = [];

        foreach ($to_constraints_missing_keys as $key) {
            $to_constraints_missing[$key] = $from_constraints[$key];
        }

        $from_constraints_missing_keys = array_diff(array_keys($this->to->ContraintsCommandsStrings()), array_keys($this->from->ContraintsCommandsStrings()));
        $from_constraints_missing      = [];

        foreach ($from_constraints_missing_keys as $key) {
            $from_constraints_missing[$key] = $to_constraints[$key];
        }

        $from_constraints_drop_keys = array_diff(array_merge(array_keys($from_constraints_missing), array_keys($from_constraints)), array_keys($to_constraints));
        $from_constraints_drop      = [];

        foreach ($from_constraints_drop_keys as $key) {
            $from_constraints_drop[$key] = $this->from->constraints[$key]->getDropCommand();
        }

        $to_constraints_drop_keys = array_diff(array_merge(array_keys($to_constraints_missing), array_keys($to_constraints)), array_keys($from_constraints));
        $to_constraints_drop      = [];

        foreach ($to_constraints_drop_keys as $key) {
            $to_constraints_drop[$key] = $this->to->constraints[$key]->getDropCommand();
        }

        // ## TODO #########
        d($from_constraints, $from_constraints_missing, $to_constraints, $to_constraints_missing, $from_constraints_drop, $to_constraints_drop);
    }

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->character_set = 'utf8 COLLATE utf8_general_ci';
        $this->config        = Config('Tools');

        $this->setEnvironment();
    }
}
