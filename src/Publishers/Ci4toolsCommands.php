<?php

/**
 * This file is part of CodeIgniter 4 Tools.
 *
 * (c) 2022 Ralf Kornberger <rakoitde@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace App\Publishers;

use CodeIgniter\Publisher\Publisher;

/**
 * This class describes a bootstrap icons publisher.
 * npm install bootstrap-icons
 */
class Ci4toolsCommands extends Publisher
{
    /**
     * Tell Publisher where to get the files.
     * Since we will use Composer to download
     * them we point to the "vendor" directory.
     *
     * @var string
     */
    protected $source = ROOTPATH . 'node_modules/bootstrap-icons';

    /**
     * FCPATH is always the default destination,
     * but we may want them to go in a sub-folder
     * to keep things organized.
     *
     * @var string
     */
    protected $destination = APPPATH . '/Commands/';

    /**
     * Use the "publish" method to indicate that this
     * class is ready to be discovered and automated.
     */
    public function publish(): bool
    {
        return $this
            // Add all the files relative to $source
            ->addPath('font')
            ->addPath('icons')

            // Merge-and-replace to retain the original directory structure
            ->merge(true);
    }

    public function __construct()
    {
        $this->wipe();
        if (! is_dir($this->destination)) {
            mkdir($this->destination, 0777, true);
        }
        parent::__construct();
    }
}
