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
 * This class describes database tools.
 */
class Structure
{

    public string $dbgroup;

    public string $table;

    protected $CHARACTER_SET = 'utf8 COLLATE utf8_general_ci';

    protected $from;

    protected $to;

    public array $from_commands = [];

    public array $to_commands = [];

    public $fields_to_update = [];

    public $fields_to_add = [];

    public $fields_to_drop = [];

    public $fields_equal = [];

    /**
     * { function_description }
     *
     * @param      <type>  $from   The from
     */
    public function from($from)
    {
        foreach ($from as $field) {
            
            $this->from[$field['Field']] = $field;
            
        }
    }

    /**
     * { function_description }
     *
     * @param      <type>  $to     { parameter_description }
     */
    public function to($to)
    {
        foreach ($to as $field) {
            
            $this->to[$field['Field']] = $field;
            
        }
    }

    /**
     * { function_description }
     */
    public function compare()
    {

        $to = $this->to;
        $from = $this->from;

        foreach ($from as $key => $field) {
                 
            if (!isset($to[$key])) {

                $this->fields_to_add[] = $key;

            } elseif ($field!==$to[$key]) {

                $this->fields_to_update[] = $key;

            } else {
                $this->fields_equal[] = $key;
            }
            
        }

        $this->fieldsToUpdate();
        $this->fieldsToAdd();

        foreach ($to as $key => $field) {
                 
            if (!isset($from[$key])) {

                $this->fields_to_drop[] = $key;

            } 
            
        }

        $this->fieldsToDrop();

    }


    public function fieldsToUpdate()
    {
        foreach ($this->fields_to_update as $field) {

            $differences = array_diff($this->from[$field], $this->to[$field]);

            if (is_array($differences) && ! empty($differences)) {
                                    // ALTER TABLE `bugs` MODIFY COLUMN `site_name`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `type`;
                                    // ALTER TABLE `bugs` MODIFY COLUMN `message`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `site_name`;
                $modify_field = "ALTER TABLE {$this->table} CHANGE `" . $this->from[$field]['Field'] . '` `' . $this->from[$field]['Field'] . '` ' . $this->from[$field]['Type'];
                if (substr($this->from[$field]['Type'], 0, 7) === 'varchar') {
                    $modify_field .= ' CHARACTER SET ' . $this->CHARACTER_SET;
                }
                $modify_field .= (isset($this->from[$field]['Default']) && $this->from[$field]['Default'] !== '') ? ' DEFAULT \'' . $this->from[$field]['Default'] . '\'' : '';
                $modify_field .= (isset($this->from[$field]['Null']) && $this->from[$field]['Null'] === 'YES') ? ' NULL' : ' NOT NULL';
                $modify_field .= (isset($this->from[$field]['Extra']) && $this->from[$field]['Extra'] !== '') ? ' ' . $this->from[$field]['Extra'] : '';
                $modify_field .= (isset($previous_field) && $previous_field !== '') ? ' AFTER ' . $previous_field : '';
                $modify_field .= ';';

                if (! in_array($modify_field, $this->to_commands, true)) {

                    $command = new Command();
                    $command->action  = 'change column';
                    $command->dbgroup = '';
                    $command->table   = $this->table;
                    $command->query   = "";
                    $command->result  = "";
                    $command->type    = "field";
                    $command->command = $modify_field;

                    $this->to_commands[] = $command;
                }
            }
            $previous_field = $this->from[$field]['Field'];

        }
    }


    public function fieldsToAdd()
    {
        foreach ($this->fields_to_add as $field) {
            $add_field = "ALTER TABLE {$this->table} ADD `" . $this->from[$field]['Field'] . '` ' . $this->from[$field]['Type'];

            if (substr($this->from[$field]['Type'], 0, 7) === 'varchar') {
                $add_field .= ' CHARACTER SET ' . $this->CHARACTER_SET;
            }

            $add_field .= (isset($this->from[$field]['Null']) && $this->from[$field]['Null'] === 'YES') ? ' Null' : ' Not Null';
            if (isset($this->from[$field]['Default'])) {
                $add_field .= ' DEFAULT ' . $this->from[$field]['Default'];
            }
            $add_field .= (isset($this->from[$field]['Extra']) && $this->from[$field]['Extra'] !== '') ? ' ' . $this->from[$field]['Extra'] : '';
            $add_field .= ';';

            if (! in_array($add_field, $this->to_commands, true)) {

                $command = new Command();
                $command->action  = 'add column';
                $command->dbgroup = '';
                $command->table   = $this->table;
                $command->query   = "";
                $command->result  = "";
                $command->type    = "field";
                $command->command = $add_field;

                $this->to_commands[] = $command;
            }
        }
    }

    
    # ALTER TABLE tbl_Country DROP COLUMN IsDeleted;
    public function fieldsToDrop()
    {
        foreach ($this->fields_to_drop as $field) {
            $drop_field = "ALTER TABLE {$this->table} DROP COLUMN `" . $this->to[$field]['Field'] . '`;';

            if (! in_array($drop_field, $this->to_commands, true)) {

                $command = new Command();
                $command->action  = 'drop column';
                $command->dbgroup = '';
                $command->table   = $this->table;
                $command->query   = "";
                $command->result  = "";
                $command->type    = "field";
                $command->command = $drop_field;

                $this->to_commands[] = $command;
            }
        }
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
    public function inArrayRecursive($needle, $haystack, $strict = false)
    {
        foreach ($haystack as $array => $item) {
            $item = $item['Field']; // look in the name field only
            if (($strict ? $item === $needle : $item === $needle) || (is_array($item) && $this->inArrayRecursive($needle, $item, $strict))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Constructs a new instance.
     *
     * @param      <type>  $table  The table
     */
    public function __construct($table)
    {
        $this->table = $table;
    }

}