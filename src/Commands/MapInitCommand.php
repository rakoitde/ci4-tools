<?php
/**
 * CI4 ETL Toolkit — initial skeleton commands
 * - map:init      → generates app/Config/ETL/map.yml from DB group `legacy` (1:1 defaults)
 * - schema:emit   → emits create-table migrations for target DB (`default`) based on map.yml
 *
 * Notes
 * - Designed to be extended (type mapping, FKs, per-field overrides from map.yml, etc.)
 * - Uses ONLY array destructuring (no extract()) as requested.
 * - YAML loading: prefers ext/yaml (yaml_parse / yaml_parse_file). Minimal fallback parser handles
 *   top-level keys + tables[*].target only; adjust as needed.
 */

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;

// -----------------------------------------------------------------------------
// map:init — create 1:1 map.yml from legacy DB
// -----------------------------------------------------------------------------
final class MapInitCommand extends BaseCommand
{
    protected $group = 'ETL';
    protected $name = 'map:init';
    protected $description = 'Create an initial 1:1 app/Config/ETL/map.yml from DB group `legacy`.';
    protected $usage = 'map:init [--group legacy] [--out app/Config/ETL/map.yml] [--include users,orders] [--exclude _migrations] [--force] [--dry-run]';

    /** @var array<string,string> */
    protected $options = [
        '--group'   => 'Source DB group (default: legacy).',
        '--out'     => 'Output path for map.yml (default: app/Config/ETL/map.yml).',
        '--include' => 'Comma-separated allowlist of tables to include.',
        '--exclude' => 'Comma-separated list of table names (or suffix/prefix patterns: %, e.g. %_tmp).',
        '--dry-run' => 'Print YAML to console instead of writing the file.',
        '--force'   => 'Overwrite existing map.yml.',
    ];

    public function run(array $params): int
    {
        $group   = (string) (CLI::getOption('group') ?? 'legacy');
        $outPath = (string) (CLI::getOption('out') ?? APPPATH . 'Config/ETL/map.yml');
        $include = $this->csvToArray((string) (CLI::getOption('include') ?? ''));
        $exclude = $this->csvToArray((string) (CLI::getOption('exclude') ?? ''));
        $dryRun  = (bool) (CLI::getOption('dry-run') ?? false);
        $force   = (bool) (CLI::getOption('force') ?? false);

        $db = Database::connect($group);
        if (!$db) throw new RuntimeException('Cannot connect DB group: ' . $group);

        $tables = $db->listTables();
        sort($tables);

        // filter include/exclude
        if (!empty($include)) {
            $tables = array_values(array_intersect($tables, $include));
        }
        if (!empty($exclude)) {
            $tables = array_values(array_filter($tables, function ($t) use ($exclude) {
                foreach ($exclude as $ex) {
                    if ($ex === $t) return false;
                    // simple % wildcard: %suffix, prefix%, %mid%
                    if (str_contains($ex, '%')) {
                        $re = '/^' . str_replace('%', '.*', preg_quote($ex, '/')) . '$/';
                        if (preg_match($re, $t)) return false;
                    }
                }
                return true;
            }));
        }

        $yaml = $this->buildYaml($tables, $group);

        if ($dryRun) {
            CLI::write("\n# map.yml (dry-run)\n" . $yaml);
            return EXIT_SUCCESS;
        }

        // ensure dir
        $dir = dirname($outPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory: ' . $dir);
        }

        if (is_file($outPath) && !$force) {
            CLI::error('File exists: ' . $outPath . ' (use --force to overwrite)');
            return EXIT_ERROR;
        }

        file_put_contents($outPath, $yaml);
        CLI::write('map.yml written: ' . $outPath, 'green');
        return EXIT_SUCCESS;
    }

    /** @return string[] */
    private function csvToArray(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn($v) => $v !== ''));
    }

    private function buildYaml(array $tables, string $sourceGroup): string
    {
        $lines = [];
        $lines[] = 'version: 1';
        $lines[] = 'source_group: ' . $sourceGroup; // legacy
        $lines[] = 'target_group: default';
        $lines[] = '';
        $lines[] = 'defaults:';
        $lines[] = '  id_strategy: keep';
        $lines[] = '  on_missing_fk: fail';
        $lines[] = '  incremental:';
        $lines[] = '    enabled: true';
        $lines[] = '    using: updated_at';
        $lines[] = '    column: updated_at';
        $lines[] = '  charset: utf8mb4';
        $lines[] = '  collate: utf8mb4_unicode_ci';
        $lines[] = '';
        $lines[] = 'tables:';
        foreach ($tables as $t) {
            $lines[] = '  ' . $t . ': {}'; // 1:1 default block
        }
        $lines[] = '';
        return implode("\n", $lines);
    }
}
