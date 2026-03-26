<?php

namespace Rakoitde\Tools\Pipeline;

use Rakoitde\Tools\Pipeline\Transformations\ConcatTransformation;
use Rakoitde\Tools\Pipeline\Transformations\DateTimeTransformation;
use Rakoitde\Tools\Pipeline\Transformations\HashTransformation;
use Rakoitde\Tools\Pipeline\Transformations\LowerTransformation;
use Rakoitde\Tools\Pipeline\Transformations\NullIfEmptyTransformation;
use Rakoitde\Tools\Pipeline\Transformations\ToIntTransformation;
use Rakoitde\Tools\Pipeline\Transformations\TrimTransformation;
use Rakoitde\Tools\Pipeline\Transformations\UpperTransformation;
use Rakoitde\Tools\Pipeline\Transformations\ValueTransformation;
use Rakoitde\Tools\Pipeline\Transformations\LookupQueryBuilderTransformation;
use Rakoitde\Tools\Pipeline\Transformations\LookupTableUpsertTransformation;
use Rakoitde\Tools\Pipeline\Transformations\SyncManyToManyTransformation;
use Rakoitde\Tools\Pipeline\Transformations\SyncOneToManyTransformation;

final class TransformationRegistry
{
    /** @var array<string, callable(array $step, object $columnSpec): TransformationInterface> */
    private array $factories = [];

    public static function withDefaultMappings(): self
    {
        $r = new self();

        // Base field transforms
        $r->register('value',        fn($s,$c) => new ValueTransformation($s['value'] ?? null));
        $r->register('trim',         fn($s,$c) => new TrimTransformation());
        $r->register('lower',        fn($s,$c) => new LowerTransformation());
        $r->register('upper',        fn($s,$c) => new UpperTransformation());
        $r->register('toInt',        fn($s,$c) => new ToIntTransformation());
        $r->register('nullIfEmpty',  fn($s,$c) => new NullIfEmptyTransformation());
        $r->register('hash',         fn($s,$c) => new HashTransformation($s['algo'] ?? 'sha256'));
        $r->register('toDateTime',   fn($s,$c) => new DateTimeTransformation($s['fmt'] ?? null));
        $r->register('concat',       fn($s,$c) => new ConcatTransformation($s['parts'] ?? [], $c->bindings ?? []));

        // QueryBuilder-driven lookup (find + optional insert)
        $r->register('lookupQb', function (array $step, object $columnSpec) {
            $findQuery   = $step['find']   ?? null; // callable
            $insertQuery = $step['insert'] ?? null; // callable|null
            $idColumn    = $step['idColumn'] ?? 'id';

            if (!is_callable($findQuery)) {
                throw new \InvalidArgumentException('lookupQb requires a callable "find" query.');
            }
            if ($insertQuery !== null && !is_callable($insertQuery)) {
                throw new \InvalidArgumentException('lookupQb "insert" must be callable or null.');
            }

            return new LookupQueryBuilderTransformation($findQuery, $insertQuery, $idColumn);
        });

        // Advanced lookup (DB-backed find-or-create)
        $r->register('lookupTableUpsert', function ($s, $c) {
            return new LookupTableUpsertTransformation(
                table:        $s['table']        ?? null,
                textColumn:   $s['textColumn']   ?? null,
                idColumn:     $s['idColumn']     ?? 'id',
                inputIsArray: (bool)($s['inputIsArray'] ?? false), // e.g. ['first_name','last_name'] → join before lookup
                joinWith:     $s['joinWith']     ?? ' ',
                createIfMissing: (bool)($s['createIfMissing'] ?? true),
                extraInsertColumns: $s['extraInsertColumns'] ?? []   // e.g. ['type' => 'user-supplied']
            );
        });

        $r->register('syncManyToMany', function (array $step, object $columnSpec) {
            $pivotTable      = $step['pivotTable']      ?? null;
            $leftIdColumn    = $step['leftIdColumn']    ?? null;
            $rightIdColumn   = $step['rightIdColumn']   ?? null;
            $leftIdSelectCol = $step['leftIdSelectColumn'] ?? 'id';
            $rightIdSource   = $step['rightIdSource']   ?? null;
            $clear           = (bool)($step['clearBeforeInsert'] ?? false);
            $dedup           = (bool)($step['deduplicate'] ?? true);

            if (!$pivotTable || !$leftIdColumn || !$rightIdColumn) {
                throw new \InvalidArgumentException('syncManyToMany requires pivotTable, leftIdColumn and rightIdColumn.');
            }

            $find = $step['itemFindQuery'] ?? null; // callable(BaseConnection $db, $token, array $row, array $context): BaseBuilder
            if (!is_callable($find)) {
                throw new \InvalidArgumentException('syncManyToMany requires callable itemFindQuery.');
            }

            $rightResolver = $step['rightIdResolver'] ?? null; // optional callable

            return new SyncManyToManyTransformation(
                pivotTable:         $pivotTable,
                leftIdColumn:       $leftIdColumn,
                rightIdColumn:      $rightIdColumn,
                itemFindQuery:      $find,
                leftIdSelectColumn: $leftIdSelectCol,
                rightIdSource:      $rightIdSource,
                rightIdResolver:    is_callable($rightResolver) ? $rightResolver : null,
                clearBeforeInsert:  $clear,
                deduplicate:        $dedup,
                syncValueToTarget:  (bool)($step['syncValueToTarget'] ?? false),

            );
        });


        $r->register('syncOneToMany', function (array $step, object $columnSpec) {
            $childTable   = $step['childTable']   ?? null;
            $parentCol    = $step['parentIdColumn'] ?? null;
            $valueCol     = $step['valueColumn']  ?? null;

            if (!$childTable || !$parentCol || !$valueCol) {
                throw new \InvalidArgumentException('syncOneToMany requires childTable, parentIdColumn and valueColumn.');
            }

            return new SyncOneToManyTransformation(
                childTable:         $childTable,
                parentIdColumn:     $parentCol,
                valueColumn:        $valueCol,
                rightIdSource:      $step['rightIdSource']      ?? null,
                rightIdResolver:    $step['rightIdResolver']    ?? null,
                clearBeforeInsert:  (bool)($step['clearBeforeInsert'] ?? false),
                deduplicate:        (bool)($step['deduplicate'] ?? true),
                syncValueToTarget:  (bool)($step['syncValueToTarget'] ?? false),
                valueNormalizer:    $step['valueNormalizer']    ?? null,
                extraInsertColumns: $step['extraInsertColumns'] ?? []
            );
        });


        return $r;
    }

    /**
     * Register a new operation factory.
     *
     * @param string   $op
     * @param callable $factory fn(array $step, object $columnSpec): TransformationInterface
     */
    public function register(string $op, callable $factory): void
    {
        $this->factories[$op] = $factory;
    }

    public function create(string $op, array $step, object $columnSpec): TransformationInterface
    {
        if (!isset($this->factories[$op])) {
            throw new \InvalidArgumentException("No transformation factory registered for op: {$op}");
        }
        return ($this->factories[$op])($step, $columnSpec);
    }
}
