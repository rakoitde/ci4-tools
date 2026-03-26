<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use CodeIgniter\Database\BaseConnection;
use Rakoitde\Tools\Pipeline\TransformationInterface;
use Rakoitde\Tools\Pipeline\SkipValue;

/**
 * Sync a one-to-many child table from a legacy field (scalar/array/JSON/CSV).
 *
 * Example: legacy 'fis_id' → target child table 'order_fis' with (order_id, fis_id).
 *
 * Context must provide:
 *   - 'db' => \CodeIgniter\Database\BaseConnection  (target DB connection)
 *
 * Constructor args:
 *   - $childTable          string   child table name, e.g. 'order_fis'
 *   - $parentIdColumn      string   FK column in child, e.g. 'order_id'
 *   - $valueColumn         string   value column in child, e.g. 'fis_id'
 *   - $rightIdSource       ?string  source row key for parent id (if not remapped)
 *   - $rightIdResolver     ?callable fn(BaseConnection $db, array $row, array $ctx): int|string|null  (preferred with remap)
 *   - $clearBeforeInsert   bool     delete child's rows for parent before inserting (default false)
 *   - $deduplicate         bool     avoid duplicate (parentId,value) pairs (default true)
 *   - $syncValueToTarget   bool     also write original field into target table (default false)
 *   - $valueNormalizer     ?callable fn($token, array $row, array $ctx): scalar|null  (null → skip)
 *   - $extraInsertColumns  array|callable  static array OR fn(BaseConnection $db, $token, array $row, array $ctx): array
 */
final class SyncOneToManyTransformation implements TransformationInterface
{
    private string $childTable;
    private string $parentIdColumn;
    private string $valueColumn;

    private ?string $rightIdSource;
    /** @var null|callable */
    private $rightIdResolver;

    private bool $clearBeforeInsert;
    private bool $deduplicate;
    private bool $syncValueToTarget;

    /** @var null|callable */
    private $valueNormalizer;

    /** @var array|callable */
    private $extraInsertColumns;

    /** deletion guard per parent id */
    private array $clearedForParent = [];

    /**
     * @param array|callable $extraInsertColumns
     * @param null|callable $valueNormalizer
     * @param null|callable $rightIdResolver
     */
    public function __construct(
        string $childTable,
        string $parentIdColumn,
        string $valueColumn,
        ?string $rightIdSource = null,
        ?callable $rightIdResolver = null,
        bool $clearBeforeInsert = false,
        bool $deduplicate = true,
        bool $syncValueToTarget = false,
        ?callable $valueNormalizer = null,
        $extraInsertColumns = []
    ) {
        $this->childTable         = $childTable;
        $this->parentIdColumn     = $parentIdColumn;
        $this->valueColumn        = $valueColumn;
        $this->rightIdSource      = $rightIdSource;
        $this->rightIdResolver    = $rightIdResolver;
        $this->clearBeforeInsert  = $clearBeforeInsert;
        $this->deduplicate        = $deduplicate;
        $this->syncValueToTarget  = $syncValueToTarget;
        $this->valueNormalizer    = $valueNormalizer;
        $this->extraInsertColumns = $extraInsertColumns;
    }

    public function apply($value, array $row = [], array $context = [])
    {
        /** @var BaseConnection|null $db */
        $db = $context['db'] ?? db_connect();
        if (!$db) {
            throw new \RuntimeException('SyncOneToManyTransformation requires $context["db"] (CodeIgniter DB).');
        }

        $parentId = $this->resolveParentId($db, $row, $context);
        if ($parentId === null || $parentId === '') {
            return $this->syncValueToTarget ? $value : SkipValue::instance();
        }

        $tokens = $this->normalizeToArray($value);
        if (empty($tokens)) {
            if ($this->clearBeforeInsert) {
                $this->clearOnce($db, $parentId);
            }
            return $this->syncValueToTarget ? $value : SkipValue::instance();
        }

        if ($this->clearBeforeInsert) {
            $this->clearOnce($db, $parentId);
        }

        foreach ($tokens as $token) {
            // normalize/cast each token
            if (is_callable($this->valueNormalizer)) {
                $token = ($this->valueNormalizer)($token, $row, $context);
            }
            // allow skipping on null
            if ($token === null || $token === '') {
                continue;
            }

            // deduplicate?
            if ($this->deduplicate) {
                $exists = $db->table($this->childTable)
                    ->select($this->parentIdColumn)
                    ->where($this->parentIdColumn, $parentId)
                    ->where($this->valueColumn, $token)
                    ->get()->getRowArray();
                if ($exists) {
                    continue;
                }
            }

            // build row
            $payload = [
                $this->parentIdColumn => $parentId,
                $this->valueColumn    => $token,
            ];

            $extra = is_callable($this->extraInsertColumns)
                ? (array) ($this->extraInsertColumns)($db, $token, $row, $context)
                : (array) $this->extraInsertColumns;

            if (!empty($extra)) {
                $payload = array_merge($payload, $extra);
            }

            $db->table($this->childTable)->insert($payload);
        }

        return $this->syncValueToTarget ? $value : SkipValue::instance();
    }

    private function resolveParentId(BaseConnection $db, array $row, array $context)
    {
        if (is_callable($this->rightIdResolver)) {
            return ($this->rightIdResolver)($db, $row, $context);
        }
        if ($this->rightIdSource !== null) {
            return $row[$this->rightIdSource] ?? null;
        }
        if (isset($context['idmap'], $row['id']) && is_object($context['idmap']) && method_exists($context['idmap'], 'get')) {
            // fallback if someone wired an idmap
            return $context['idmap']->get('parent', $row['id']);
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
            // try JSON array
            $decoded = json_decode($v, true);
            if (is_array($decoded)) return $decoded;
            // fallback: comma separated
            return array_map('trim', explode(',', $v));
        }
        if ($value === null || $value === false) {
            return [];
        }
        // scalar → single element
        return [$value];
    }

    private function clearOnce(BaseConnection $db, $parentId): void
    {
        $key = (string) $parentId;
        if (isset($this->clearedForParent[$key])) {
            return;
        }
        $db->table($this->childTable)
            ->where($this->parentIdColumn, $parentId)
            ->delete();
        $this->clearedForParent[$key] = true;
    }
}
