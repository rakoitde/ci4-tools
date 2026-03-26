<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Rakoitde\Tools\Pipeline\TransformationInterface;
use Rakoitde\Tools\Pipeline\SkipValue;


/**
 * Synchronize a many-to-many pivot table from an array-like field.
 *
 * Example: value ["Maurice","Marcel"] →
 *   - find users.id for each token via $itemFindQuery (QueryBuilder factory)
 *   - insert into pivot: (users_id => found_id, contract_id => resolved_right_id)
 *   - optionally clear existing pivot rows for this contract_id before inserting
 *
 * Context must provide:
 *   - 'db' => \CodeIgniter\Database\BaseConnection  (target DB connection)
 * Optional in context if you remap IDs:
 *   - 'idmap' => object with method get(string $table, $legacyId): int|string|null
 *
 * Constructor:
 *   - $pivotTable           string              e.g. 'contract_users'
 *   - $leftIdColumn         string              e.g. 'users_id'
 *   - $rightIdColumn        string              e.g. 'contract_id'
 *   - $itemFindQuery        callable(BaseConnection $db, $token, array $row, array $context): BaseBuilder
 *   - $leftIdSelectColumn   string              column selected by $itemFindQuery (default 'id')
 *   - $rightIdSource        ?string             source-row key to read the RIGHT id from (e.g. 'id')
 *   - $rightIdResolver      ?callable           fn(BaseConnection $db, array $row, array $context): int|string|null (overrides rightIdSource)
 *   - $clearBeforeInsert    bool                delete pivot rows by RIGHT id before insert
 *   - $deduplicate          bool                avoid duplicate insert if exists (default true)
 */
final class SyncManyToManyTransformation implements TransformationInterface
{
    private string $pivotTable;
    private string $leftIdColumn;
    private string $rightIdColumn;

    /** @var callable */
    private $itemFindQuery;
    private string $leftIdSelectColumn;

    private ?string $rightIdSource;
    /** @var null|callable */
    private $rightIdResolver;

    private bool $clearBeforeInsert;
    private bool $deduplicate;

    /** @var array<string,bool> deletion guard per right-id to avoid repeated clears within one row */
    private array $clearedForRightId = [];
    private bool $syncValueToTarget;

    public function __construct(
        string $pivotTable,
        string $leftIdColumn,
        string $rightIdColumn,
        callable $itemFindQuery,
        string $leftIdSelectColumn = 'id',
        ?string $rightIdSource = null,
        ?callable $rightIdResolver = null,
        bool $clearBeforeInsert = false,
        bool $deduplicate = true,
        bool $syncValueToTarget = false
    ) {
        $this->pivotTable         = $pivotTable;
        $this->leftIdColumn       = $leftIdColumn;
        $this->rightIdColumn      = $rightIdColumn;
        $this->itemFindQuery      = $itemFindQuery;
        $this->leftIdSelectColumn = $leftIdSelectColumn;
        $this->rightIdSource      = $rightIdSource;
        $this->rightIdResolver    = $rightIdResolver;
        $this->clearBeforeInsert  = $clearBeforeInsert;
        $this->deduplicate        = $deduplicate;
        $this->syncValueToTarget  = $syncValueToTarget;
    }

    public function apply($value, array $row = [], array $context = [])
    {
        /** @var BaseConnection|null $db */
        $db = $context['db'] ?? db_connect();
        if (!$db) {
            throw new \RuntimeException('SyncManyToManyTransformation requires $context["db"] (CodeIgniter DB).');
        }

        $rightId = $this->resolveRightId($db, $row, $context);
        if ($rightId === null || $rightId === '') {
            // Nichts zu verknüpfen – ggf. Feld in Ziel behalten?
            return $this->syncValueToTarget ? $value : SkipValue::instance();  // <-- PATCH
        }

        $tokens = $this->normalizeToArray($value);
        if (empty($tokens)) {
            if ($this->clearBeforeInsert) {
                $this->clearOnce($db, $rightId);
            }
            return $this->syncValueToTarget ? $value : SkipValue::instance();  // <-- PATCH
        }

        if ($this->clearBeforeInsert) {
            $this->clearOnce($db, $rightId);
        }

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            /** @var BaseBuilder $findBuilder */
            $findBuilder = ($this->itemFindQuery)($db, $token, $row, $context);
            $found       = $findBuilder->get()->getRowArray();

            if (!$found || !array_key_exists($this->leftIdSelectColumn, $found)) {
                continue;
            }

            $leftId = $found[$this->leftIdSelectColumn];

            if ($this->deduplicate) {
                $exists = $db->table($this->pivotTable)
                    ->select($this->rightIdColumn)
                    ->where($this->rightIdColumn, $rightId)
                    ->where($this->leftIdColumn, $leftId)
                    ->get()->getRowArray();

                if ($exists) {
                    continue;
                }
            }

            $db->table($this->pivotTable)->insert([
                $this->rightIdColumn => $rightId,
                $this->leftIdColumn  => $leftId,
            ]);
        }

        // Rückgabe entscheidet, ob Feld in Zieltabelle landet
        return $this->syncValueToTarget ? $value : SkipValue::instance();      
    }

    private function resolveRightId(BaseConnection $db, array $row, array $context)
    {
        if (is_callable($this->rightIdResolver)) {
            return ($this->rightIdResolver)($db, $row, $context);
        }
        if ($this->rightIdSource !== null) {
            // read from source row; if IDs are remapped, caller can pass a resolver instead
            return $row[$this->rightIdSource] ?? null;
        }
        // As a last resort: try idmap service from context
        if (isset($context['idmap'], $row['id']) && is_object($context['idmap']) && method_exists($context['idmap'], 'get')) {
            // Example: $context['idmap']->get('contract', $row['id'])
            return $context['idmap']->get($this->pivotTable /* or configured table */, $row['id']);
        }
        return null;
    }

    private function normalizeToArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $v = trim($value);
            if ($v === '') return [];
            // Try JSON decode
            $decoded = json_decode($v, true);
            if (is_array($decoded)) return $decoded;
            // Fallback: comma-separated list
            return array_map('trim', explode(',', $v));
        }
        return [];
    }

    private function clearOnce(BaseConnection $db, $rightId): void
    {
        $key = (string)$rightId;
        if (isset($this->clearedForRightId[$key])) {
            return;
        }
        $db->table($this->pivotTable)
            ->where($this->rightIdColumn, $rightId)
            ->delete();
        $this->clearedForRightId[$key] = true;
    }
}
