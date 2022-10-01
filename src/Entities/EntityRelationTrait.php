<?php

/**
 * This file is part of the CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rakoitde\Tools\Entities;

use Throwable;

/**
 * EntityRelationTrait contains a collection of methods
 * to extend the Entity model class with relations functionality.
 */
trait EntityRelationTrait
{

    public function many($many, $manyForeignKey = null)
    {

        $thisModel = model(str_replace(["Entity","Entities"], ["Model","Models"], get_class($this)));
        $this_pk = $thisModel->primaryKey;

        $manyModel = model($many);
        $many_pk = $manyModel->primaryKey;

        $keys = $manyModel->db->getForeignKeyData($manyModel->table);
        $db_this_fk = $db_many_fk = null;
        
        foreach ($keys as $key) {
            if ($key->foreign_table_name==$manyModel->table && $key->foreign_column_name==$many_pk) { 
                    $db_many_fk = $key->column_name; 
            }            
        }

        $many_fk = $manyForeignKey ?? $db_many_fk ?? $thisModel->table.'_'.$this_pk;

        $many = $manyModel
            ->select($manyModel->table.'.*')
            ->where($manyModel->table.'.'.$many_fk, $this->$this_pk);

        return $many;
    }

    public function findMany($many, $manyForeignKey = null)
    {
        return $this->many($many, $manyForeignKey)->findAll();
    }

    public function manyOver($over, $many, $manyForeignKey = null, $thisForeignKey = null)
    {

        $thisModel = model(str_replace(["Entity","Entities"], ["Model","Models"], get_class($this)));
        $this_pk = $thisModel->primaryKey;

        $manyModel = model($many);
        $many_pk = $manyModel->primaryKey;

        $overModel = model($over);
        $over_pk = $overModel->primaryKey;

        $keys = $manyModel->db->getForeignKeyData($overModel->table);
        $db_this_fk = $db_many_fk = null;
        
        foreach ($keys as $key) {
            if ($key->foreign_table_name==$thisModel->table && $key->foreign_column_name==$thisModel->primaryKey) { 
                    $db_this_fk = $key->column_name; 
            }

            if ($key->foreign_table_name==$manyModel->table && $key->foreign_column_name==$many_pk) { 
                    $db_many_fk = $key->column_name; 
            }            
        }

        $this_fk = $thisForeignKey ?? $db_this_fk ?? $thisModel->table.'_'.$thisModel->primaryKey;
        $many_fk = $manyForeignKey ?? $db_many_fk ?? $manyModel->table.'_'.$many_pk;

        $many = $manyModel
            ->select($manyModel->table.'.*')
            ->join($overModel->table, $overModel->table.'.'.$many_fk.' = '.$manyModel->table.'.'.$many_pk, 'left')
            ->where($overModel->table.'.'.$this_fk, $this->$this_pk);

        return $many;
    }

    public function findManyOver($over, $many, $manyForeignKey = null, $thisForeignKey = null)
    {
        return $this->manyOver($over, $many, $manyForeignKey, $thisForeignKey)->findAll();
    }

}