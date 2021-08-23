<?php

namespace Rakoitde\Tools\Entities;

use CodeIgniter\Entity;

class BackupJobsEntity extends Entity
{
	protected $datamap = [];

	protected $dates = [
		'created_at',
		'updated_at',
		'deleted_at',
	];

	protected $casts = [];

}
