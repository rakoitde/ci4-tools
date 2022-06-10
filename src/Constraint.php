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
 * This class describes a Constraint.
 */

class Constraint
{

    public $name;

    public $table_name;

    public $column_name;

    public $referenced_table_name;

    public $referenced_column_name;

    public $delete_rule;

    public $update_rule;

    public function fill($constraint)
    {
        $this->name                   = $constraint["CONSTRAINT_NAME"];
        $this->table_name             = $constraint["TABLE_NAME"];
        $this->column_name            = $constraint["COLUMN_NAME"];
        $this->referenced_table_name  = $constraint["REFERENCED_TABLE_NAME"];
        $this->referenced_column_name = $constraint["REFERENCED_COLUMN_NAME"];
        $this->delete_rule            = $constraint["DELETE_RULE"];
        $this->update_rule            = $constraint["UPDATE_RULE"];
    }


    public function getDropCommand()
    {
        $command = "ALTER TABLE `{$this->table_name}` DROP FOREIGN KEY `{$this->name}`;";
        return $command;
    }

    public function getAddCommand()
    {
        $command = "ALTER TABLE `{$this->table_name}` ADD CONSTRAINT `{$this->name}` FOREIGN KEY (`{$this->column_name}`) REFERENCES `{$this->referenced_table_name}` (`{$this->referenced_column_name}`) ON DELETE {$this->delete_rule} ON UPDATE {$this->update_rule};";
        return $command;
    }

    public function __toString()
    {
        return $this->getAddCommand();
    }

    public function __construct($constraint)
    {
        $this->fill($constraint);
    }

}