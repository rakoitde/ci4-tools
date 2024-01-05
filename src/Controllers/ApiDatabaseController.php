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

use CodeIgniter\RESTful\ResourceController;
use Rakoitde\Tools\DatabaseTools as DbTools;

class ApiDatabaseController extends ResourceController
{
    protected DbTools $dbtools;

    public function environment()
    {
        return $this->respond($this->dbtools->getEnvironment());
    }

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return mixed
     */
    public function tables()
    {
        $tables = [
            'from'      => $this->dbtools->from->tables(),
            'to'        => $this->dbtools->to->tables(),
            'tocompare' => $this->dbtools->getTablesToCompare(),
        ];

        return $this->respond($tables);
    }

    /**
     * Return the properties of a resource object
     *
     * @param mixed $table
     *
     * @return mixed
     */
    public function compare($table)
    {
        return $this->respond($this->dbtools->compare($table));
    }

    /**
     * Return a new resource object, with default properties
     *
     * @return mixed
     */
    public function new()
    {
    }

    /**
     * Create a new resource object, from "posted" parameters
     *
     * @return mixed
     */
    public function create()
    {
        if (model('DocumentModel')->save($this->request->getPost())) {
            return $this->respond($this->request->getPost());
        }

        return $this->fail($this->request->getPost());
    }

    /**
     * Return the editable properties of a resource object
     *
     * @param mixed|null $id
     *
     * @return mixed
     */
    public function edit($id = null)
    {
        $document = model('DocumentModel')->find($id);

        return $this->respond($document);
    }

    /**
     * Add or update a model resource, from "posted" properties
     *
     * @param mixed|null $id
     *
     * @return mixed
     */
    public function update($id = null)
    {
        if (model('DocumentModel')->update($id, $this->request->getPost())) {
            return $this->respond($this->request->getPost());
        }

        return $this->fail($this->request->getPost());
    }

    /**
     * Delete the designated resource object from the model
     *
     * @param mixed|null $id
     *
     * @return mixed
     */
    public function delete($id = null)
    {
        if (model('DocumentModel')->delete($id)) {
            return $this->respondDeleted(['id' => $id]);
        }

        return $this->fail(['id' => $id]);
    }

    public function __construct()
    {
        $this->dbtools = \Rakoitde\Tools\Config\Services::dbtools();
    }
}
