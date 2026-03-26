<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Rakoitde\Tools\Pipeline\TransformationInterface;

/**
 * QueryBuilder-based lookup that returns an ID (or any scalar),
 * with optional "insert if missing" behavior.
 *
 * Context MUST provide:
 *   - 'db' => \CodeIgniter\Database\BaseConnection  (target DB)
 *
 * Constructor parameters:
 *  - $findQuery:   callable(BaseConnection $db, array $row, array $context): BaseBuilder
 *      Must return a builder that SELECTs the ID column defined by $idColumn.
 *
 *  - $insertQuery: null|callable(BaseConnection $db, array $row, array $context): array|BaseBuilder|int|string
 *      If provided and a record is NOT found, this is called to create the new record.
 *      Supported returns:
 *        a) array ['table' => 'table_name', 'data' => [col => val, ...]]
 *           → the transformer will call $db->table(table)->insert(data)
 *        b) BaseBuilder prepared for insertion
 *           → the transformer will call $builder->insert()
 *        c) int|string (already a new ID to return)
 *
 *  - $idColumn:     string  (defaults to 'id'; must be selected by the find builder)
 */
final class LookupQueryBuilderTransformation implements TransformationInterface
{
    /** @var callable */
    private $findQuery;

    /** @var null|callable */
    private $insertQuery;

    private string $idColumn;

    /**
     * @param callable $findQuery
     * @param null|callable $insertQuery
     * @param string $idColumn
     */
    public function __construct(callable $findQuery, ?callable $insertQuery = null, string $idColumn = 'id')
    {
        $this->findQuery  = $findQuery;
        $this->insertQuery = $insertQuery;
        $this->idColumn   = $idColumn;
    }

    public function apply($value, array $row = [], array $context = [])
    {
        /** @var BaseConnection|null $db */
        $db = $context['db'] ?? db_connect();
        if (!$db) {
            throw new \RuntimeException('LookupQueryBuilderTransformation requires $context["db"] (CodeIgniter DB).');
        }

        // 1) Build and run the SELECT
        /** @var BaseBuilder $findBuilder */
        $findBuilder = ($this->findQuery)($db, $row, $context);
        $result      = $findBuilder->get()->getRowArray();

        if ($result && array_key_exists($this->idColumn, $result)) {
            return $result[$this->idColumn];
        }

        // 2) Not found → maybe insert
        if ($this->insertQuery === null) {
            return null;
        }

        $db->transStart();

        // Caller may provide the new ID directly, or an insert plan/builder
        $newId = ($this->insertQuery)($db, $row, $context);

        // c) Direct new ID (int|string) returned by callback
        if (is_int($newId) || is_string($newId)) {
            $db->transComplete();
            return $newId;
        }

        // a) Array plan: ['table' => ..., 'data' => [...]]
        if (is_array($newId) && isset($newId['table'], $newId['data']) && is_array($newId['data'])) {
            $db->table($newId['table'])->insert($newId['data']);
            $newId = $db->insertID();
            $db->transComplete();
            return $newId ?: null;
        }

        // b) Builder plan: a prepared BaseBuilder that we just ->insert()
        if ($newId instanceof BaseBuilder) {
            $newId->insert();
            $newId = $db->insertID();
            $db->transComplete();
            return $newId ?: null;
        }

        // Unsupported insert type
        $db->transComplete();
        throw new \InvalidArgumentException('Unsupported insert plan returned by $insertQuery.');
    }
}
