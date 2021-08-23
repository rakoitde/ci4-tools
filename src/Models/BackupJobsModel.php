<?php

namespace Rakoitde\Idoit\Models;

use CodeIgniter\Model;

class BackupJobsModel extends Model
{
	protected $table      = 'backup_jobs';
	protected $primaryKey = 'id';
	protected $useAutoIncrement = true;

	protected $insertID = 0;
	protected $DBGroup  = 'default';

	protected $returnType     = 'Rakoitde\Tools\Entities\BackupJobsEntity';
	protected $useSoftDeletes = false;
	protected $allowedFields  = ['jobname', 'dbgroup', 'tables', 'destination', 'mailto'];

	// Dates
	protected $useTimestamps = true;
	protected $dateFormat    = 'datetime';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';
	protected $deletedField  = 'deleted_at';
	protected $protectFields = true;

	// Validation
	protected $validationRules      = [
		'jobname'        => 'required|alpha_numeric_space|min_length[3]|is_unique[backup_jobs.jobname,jobname,{jobname}]',
		'dbgroup' => 'required|alpha_numeric_space',
	];
	protected $validationMessages   = [];
	protected $skipValidation       = true;
	protected $cleanValidationRules = true;

	// Callbacks
	protected $allowCallbacks = true;
	protected $beforeInsert   = [];
	protected $afterInsert    = [];
	protected $beforeUpdate   = [];
	protected $afterUpdate    = [];
	protected $beforeFind     = [];
	protected $afterFind      = [];
	protected $beforeDelete   = [];
	protected $afterDelete    = [];
}
