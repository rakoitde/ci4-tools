<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Rakoitde\Tools\Controllers;

use App\Controllers\BaseController;

use CodeIgniter\I18n\Time;

/**
 * This class describes a database backup.
 */
class DatabaseBackup extends BaseController
{
    protected $db;
    protected array $tables;
    protected bool $createTableIfNotExists = true;
    protected string $modelname            = '\Rakoitde\Tools\Models\BackupJobsModel';
    protected $model;
    protected string $entityname = '\Rakoitde\Tools\Entity\BackupJobsEntity';
    protected $helpers           = ['html'];

    /**
     * { function_description }
     *
     * @param      <type>  $id     The identifier
     *
     * @return  <type>  ( description_of_the_return_value )
     */
    public function index($id = null)
    {
        $myTime = new Time('now', 'Europe/Berlin', 'de_DE');
        //d($myTime);
        $myTime = new Time('now');
        //d("full_backup_".str_replace(" ", "_", $myTime->toDateTimeString() ).".sql");

        //d($this->model);

        // Collect Data
        $job  = isset($id) ? $this->model->find($id) : $this->model->first();
        $data = [
            'createsql'  => $this->backup('auth_groups_permissions'), //$this->backup(),
            'backupjobs' => $this->model->orderBy('jobname')->findAll(),
            'job'        => $job,
            'db'         => \Config\Database::connect($job['dbgroup']),
        ];
        $data['tables'] = $data['db']->listTables();
        //d($data['tables']);
        return view('Rakoitde\Tools\Views\DatabaseBackupView', $data);
    }

    /**
     * { function_description }
     *
     * @param string $table The table
     *
     * @return string ( description_of_the_return_value )
     */
    public function backup(?string $table = null)
    {
        // Get All Table Names From the Database
        $tables = $this->db->listTables();

        if (in_array($table, $tables, true)) {
            $tables = [$table];
        }

        $sqlScript = '';

        foreach ($tables as $table) {
            // Prepare SQLscript for creating table structure
            $createTable = $this->db->query("SHOW CREATE TABLE {$table}")->getResultArray()[0];

            if ($this->createTableIfNotExists && isset($createTable['Create Table'])) {
                $createTable['Create Table'] = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createTable['Create Table']);
            }

            if (isset($createTable['Create Table'])) {
                $sqlScript .= $createTable['Create Table'] . ";\n\n";

                $query       = $this->db->query("SELECT * FROM {$table}");
                $rows        = $query->getResultArray();
                $fieldNames  = $query->getFieldNames();
                $columnCount = $query->getFieldCount();

                // Prepare SQLscript for dumping data for each table
                foreach ($rows as $row) {
                    $sqlScript .= "INSERT INTO {$table} VALUES(";

                    for ($j = 0; $j < $columnCount; $j++) {
                        //$row[$j] = $row[$j];

                        if (isset($row[$fieldNames[$j]])) {
                            $sqlScript .= '"' . $row[$fieldNames[$j]] . '"';
                        } else {
                            $sqlScript .= '""';
                        }
                        if ($j < ($columnCount - 1)) {
                            $sqlScript .= ',';
                        }
                    }

                    $sqlScript .= ");\n";
                }
            } elseif (isset($createTable['Create View'])) {
                $sqlScript .= $createTable['Create View'];
            } else {
                $sqlScript .= '-- no views or tables to create';
            }

            $sqlScript .= "\n";
        }

        return $sqlScript;
    }

    /**
     * Downloads a backup file.
     *
     * @param      <type>  $sqlScript  The sql script
     */
    public function downloadBackupFile($sqlScript)
    {
        if (! empty($sqlScript)) {
            // Save the SQL script to a backup file
            $backup_file_name = 'default' . '_backup_' . time() . '.sql';
            $fileHandler      = fopen($backup_file_name, 'w+b');
            $number_of_lines  = fwrite($fileHandler, $sqlScript);
            fclose($fileHandler);

            // Download the SQL backup file to the browser
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backup_file_name));
            ob_clean();
            flush();
            readfile($backup_file_name);
            exec('rm ' . $backup_file_name);
        }
    }

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->db    = \Config\Database::connect(); // load the source/
        $this->model = model($this->modelname);
    }
}
