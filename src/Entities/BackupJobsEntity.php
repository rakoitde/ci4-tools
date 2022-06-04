<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Rakoitde\Tools\Entities;

use CodeIgniter\Entity;

/**
 * This class describes a backup jobs entity.
 * */
class BackupJobsEntity extends Entity
{
    /**
     * [$datamap description]
     */
    protected array $datamap = [];

    /**
     * [$dates description]
     */
    protected array $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * [$casts description]
     */
    protected array $casts = [];
}
