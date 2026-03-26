<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rakoitde\Tools\Support\ConfigLoader;
use Rakoitde\Tools\Domain\FKGraph;
use Rakoitde\Tools\Domain\Pipeline;
use Rakoitde\Tools\Runtime\SyncState;
use Rakoitde\Tools\Runtime\UpsertWriter;
use Rakoitde\Tools\Runtime\BatchIterator;

class LegacySyncCommand extends BaseCommand
{
    protected $group       = 'legacy';
    protected $name        = 'legacy:sync';
    protected $description = 'Synchronize data from the legacy database into the new schema directly (full or incremental per table).';

    protected $usage     = 'php spark legacy:sync --config=Legacy/Map [--mode=auto|full|incremental] [--tables=list] [--since=value] [--dry-run]';
    protected $arguments = [];
    protected $options   = [
        '--config'  => 'Config path (e.g. Legacy/Map or absolute file path).',
        '--mode'    => 'Force mode for all tables: auto|full|incremental (default: auto, uses DSL per table).',
        '--tables'  => 'Comma-separated list of target tables to sync (subset).',
        '--since'   => 'Override the High-Water-Mark for incremental sync (e.g. 2025-01-01T00:00:00).',
        '--dry-run' => 'Preview without writing any changes.',
    ];

    public function run(array $params)
    {
        $targetDb  = \Config\Database::connect();
        $legacyDb  = \Config\Database::connect('legacy');
        $configSet = $this->loadConfig($params);
        $fkOrder   = FKGraph::topo($configSet->tables);
        $syncState    = new SyncState($targetDb);
        $upsertWriter = new UpsertWriter($targetDb);

        // CLI options
        [$tablesFilter, $forcedMode, $isDryRun, $sinceOverride] = $this->readCliOptions();

        foreach ($fkOrder as $targetTableName) {
            if ($tablesFilter && !in_array($targetTableName, $tablesFilter, true)) {
                CLI::write("Skip {$targetTableName} (not in filter)", 'yellow');
                continue;
            }

            $tableConfig = $configSet->get($targetTableName);
            if (!$tableConfig) {
                CLI::write("Skip {$targetTableName} (no config found)", 'yellow');
                continue;
            }

            $mode = $this->resolveMode($tableConfig->syncMode ?? 'incremental', $forcedMode);
            if ($mode === 'disabled') {
                CLI::write("Skip {$targetTableName} (disabled)", 'yellow');
                continue;
            }

            $pipeline      = Pipeline::compile($tableConfig);
            $storedState   = $syncState->get($tableConfig->target);
            $watermark     = $this->resolveInitialWatermark($storedState, $sinceOverride);

            // A fresh source-builder per batch, including WHERE/HWM filters
            $sourceQuery = $this->createSourceQuery($legacyDb, $tableConfig, $mode, $watermark);

            // Read → transform → upsert → track watermark
            [$rowsUpserted, $maxWatermark] = $this->processBatches(
                $sourceQuery,
                $tableConfig->batchSize ?? 1000,
                $pipeline,
                $upsertWriter,
                $tableConfig,
                $mode,
                $watermark,
                $isDryRun
            );

            // Summary
            $this->printSummary($targetTableName, $rowsUpserted, $mode, $maxWatermark, $isDryRun);

            // Persist sync state (skip on dry-run)
            if (!$isDryRun) {
                $this->persistState(
                    $syncState,
                    $tableConfig->target,
                    $tableConfig->watermarkType ?? 'datetime',
                    $rowsUpserted,
                    $maxWatermark
                );
            }
        }
    }

    /**
     * Load mapping configuration from:
     *  - a class name (short or FQCN), or
     *  - a file path under APPPATH/Database/Sync, or
     *  - a file that returns an array of Table definitions.
     *
     * Expected: a class exposing public $table (array of Table).
     * The optional $tables property is ignored.
     */
    private function loadConfig(array $params)
    {
        $arg = $params[0] ?? CLI::getOption('config') ?? 'SyncMap';

        // Normalize names
        $trimmed = ltrim($arg, '\\/');
        $short   = str_replace(['/', '.'], ['\\', '\\'], $trimmed);
        $base    = basename(str_replace('\\', '/', $short));

        // Candidate classes (prefer App\Database\Sync\*, then Config\*)
        $candidates = [];
        if (str_contains($short, '\\')) {
            // FQCN provided
            $candidates[] = '\\' . ltrim($short, '\\');
        } else {
            // Short name: try likely namespaces
            $candidates[] = '\\App\\Database\\Sync\\' . $base;
            $candidates[] = '\\Config\\' . $base;
        }

        // 1) If any candidate class already exists, use it.
        foreach ($candidates as $class) {
            if (class_exists($class)) {
                $instance = new $class();
                $tables   = $instance->table ?? [];
                if (!is_array($tables)) {
                    throw new \RuntimeException("$class::\$table must be an array of Table definitions.");
                }
                return ConfigLoader::fromArray($tables);
        }
        }

        // 2) Resolve file path (explicit path OR APPPATH/Database/Sync/<Name>.php)
        $path = is_file($arg)
            ? $arg
            : APPPATH . 'Database/Sync/' . $base . '.php';

        if (!is_file($path)) {
            $pathsTried = [$path];
            // Also try with namespace-like relative string converted to path
            $alt = APPPATH . 'Database/Sync/' . str_replace('\\', '/', $short) . '.php';
            if ($alt !== $path) {
                $pathsTried[] = $alt;
            }
            $pathsTxt = implode(' | ', $pathsTried);
            throw new \RuntimeException("Sync map not found as class (".implode(', ', $candidates).") or file ($pathsTxt)");
        }

        // 3) Require the file ONCE and capture return value (if any).
        //    If the file declares a class, it will now be loadable via class_exists.
        $returned = (static function ($p) { return require $p; })($path);

        // 4) After requiring, try the candidate classes again.
        foreach ($candidates as $class) {
            if (class_exists($class)) {
                $instance = new $class();
                $tables   = $instance->table ?? [];
                if (!is_array($tables)) {
                    throw new \RuntimeException("$class::\$table must be an array of Table definitions.");
                }
                return ConfigLoader::fromArray($tables);
            }
        }

        // 5) As a fallback, allow the file to return an array of Table definitions.
        if (is_array($returned)) {
            return ConfigLoader::fromArray($returned);
        }

        throw new \RuntimeException(
            "Unable to load sync configuration from '$arg'. ".
            "Make sure a class exists (".implode(', ', $candidates).") exposing public \$table (array), ".
            "or the file returns an array of Table definitions."
        );
    }


    /**
     * Parse CLI options for filtering, mode, dry-run and watermark override.
     *
     * @return array{0:?string[],1:?string,2:bool,3:?string} [tablesFilter, forcedMode, isDryRun, sinceOverride]
     */
    private function readCliOptions(): array
    {
        $tablesOption = CLI::getOption('tables');
        $tablesFilter = $tablesOption ? array_map('trim', explode(',', $tablesOption)) : null;

        $forcedMode    = CLI::getOption('mode'); // auto|full|incremental|null
        $isDryRun      = (bool) CLI::getOption('dry-run');
        $sinceOverride = CLI::getOption('since') ?: null;

        return [$tablesFilter, $forcedMode, $isDryRun, $sinceOverride];
    }

    /**
     * Resolve the effective mode (auto → table DSL; otherwise global override).
     */
    private function resolveMode(string $tableMode, ?string $forcedMode): string
    {
        if ($forcedMode && $forcedMode !== 'auto') {
            return $forcedMode;
        }
        return $tableMode;
    }

    /**
     * Resolve the initial watermark from stored sync state and optional --since override.
     */
    private function resolveInitialWatermark(array $stateRow, ?string $sinceOverride): ?string
    {
        if ($sinceOverride !== null && $sinceOverride !== '') {
            return $sinceOverride;
        }
        return $stateRow['last_watermark'] ?? null;
    }

    /**
     * Create a closure that returns a fresh legacy DB builder with all filters applied.
     * The closure is used by the BatchIterator to build the paged queries.
     *
     * @return \Closure(): \CodeIgniter\Database\BaseBuilder
     */
    private function createSourceQuery($legacyDb, $tableConfig, string $mode, ?string $watermark): \Closure
    {
        return function () use ($legacyDb, $tableConfig, $mode, $watermark) {
            $builder = $legacyDb->table($tableConfig->source);

            if (!empty($tableConfig->where)) {
                foreach ($tableConfig->where as $column => $value) {
                    $builder->where($column, $value);
                }
            }

            if ($mode === 'incremental') {
                if (!empty($tableConfig->changeLogTable)) {
                    // Optional change-log mode
                    $builder = $builder->db()->table($tableConfig->changeLogTable);
                    $sinceColumn = $tableConfig->changeLogSince ?? 'id';
                    if ($watermark) {
                        $builder->where($sinceColumn . ' >', $watermark);
                    }
                } elseif (!empty($tableConfig->incrementalBy)) {
                    if ($watermark) {
                        $builder->where($tableConfig->incrementalBy . ' >', $watermark);
                    }
                }
            }

            return $builder;
        };
    }

    /**
     * Execute the batch loop: read → transform → upsert, and track the watermark.
     *
     * @return array{0:int,1:?string} [rowsUpserted, maxWatermark]
     */
    private function processBatches(
        \Closure $sourceQuery,
        int $batchSize,
        $pipeline,
        UpsertWriter $upsertWriter,
        $tableConfig,
        string $mode,
        ?string $initialWatermark,
        bool $isDryRun
    ): array {
        $iterator     = new BatchIterator($sourceQuery, $batchSize);
        $rowsUpserted = 0;
        $maxWatermark = $initialWatermark;

        foreach ($iterator as $rows) {
            $transformedRows = [];

            foreach ($rows as $row) {
                $mapped = $pipeline->apply($row, context: []);
                $transformedRows[] = $mapped;

                $wmColumn = $this->determineWatermarkColumn($tableConfig);
                if ($wmColumn && isset($row[$wmColumn])) {
                    $candidate = (string) $row[$wmColumn];
                    if ($maxWatermark === null || strcmp($candidate, (string) $maxWatermark) > 0) {
                        $maxWatermark = $candidate;
                    }
                }
            }

            if (!$isDryRun && $transformedRows) {
                $rowsUpserted += $upsertWriter->upsert($tableConfig, $transformedRows);
            }
        }

        return [$rowsUpserted, $maxWatermark];
    }

    /**
     * Determine which column acts as the watermark (HWM).
     */
    private function determineWatermarkColumn($tableConfig): ?string
    {
        if (!empty($tableConfig->changeLogTable)) {
            return $tableConfig->changeLogSince ?: 'id';
        }
        if (!empty($tableConfig->incrementalBy)) {
            return $tableConfig->incrementalBy;
        }
        return null;
    }

    /**
     * Persist the updated sync state.
     */
    private function persistState(
        SyncState $syncState,
        string $targetTable,
        string $watermarkType,
        int $rowsUpserted,
        ?string $maxWatermark
    ): void {
        $syncState->update($targetTable, [
            'last_watermark' => $maxWatermark,
            'watermark_type' => $watermarkType,
            'last_run_at'    => date('Y-m-d H:i:s'),
            'rows_upserted'  => $rowsUpserted,
        ]);
    }

    /**
     * Print a concise per-table summary.
     */
    private function printSummary(string $targetTable, int $rowsUpserted, string $mode, ?string $maxWatermark, bool $isDryRun): void
    {
        $prefix = $isDryRun ? '[DRY] ' : '';
        $wm     = $maxWatermark ?? 'NULL';
        CLI::write(sprintf('%sSynced %s rows=%d mode=%s hwm=%s', $prefix, $targetTable, $rowsUpserted, $mode, $wm));
    }
}
