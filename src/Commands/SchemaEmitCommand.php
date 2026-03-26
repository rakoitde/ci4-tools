<?php
/**
 * CI4 ETL Toolkit — initial skeleton commands
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
// schema:emit — generate create-table migrations for target DB from map.yml
// -----------------------------------------------------------------------------
final class SchemaEmitCommand extends BaseCommand
{
    protected $group = 'ETL';
    protected $name = 'schema:emit';
    protected $description = 'Emit create-table migrations for target DB (default) from map.yml (1:1 and basic target overrides).';
    protected $usage = 'schema:emit [--map app/Config/ETL/map.yml] [--namespace App] [--dry-run] [--force] [--only users,orders]';

    /** @var array<string,string> */
    protected $options = [
        '--map'       => 'Path to map.yml (default: app/Config/ETL/map.yml).',
        '--namespace' => 'Target namespace for migrations (default: App).',
        '--only'      => 'Comma-separated list of tables to emit (by legacy name or target name).',
        '--dry-run'   => 'Show planned migrations without writing files.',
        '--force'     => 'Write files (requires this flag).',
    ];

    public function run(array $params): int
    {
        $mapPath   = (string) (CLI::getOption('map') ?? APPPATH . 'Config/ETL/map.yml');
        $ns        = (string) (CLI::getOption('namespace') ?? 'App');
        $onlyList  = $this->csvToArray((string) (CLI::getOption('only') ?? ''));
        $dryRun    = (bool)   (CLI::getOption('dry-run') ?? false);
        $force     = (bool)   (CLI::getOption('force') ?? false);

        [$map, $sourceGroup, $targetGroup] = $this->loadMapLite($mapPath);
        $dbSrc  = Database::connect($sourceGroup);
        $dbDest = Database::connect($targetGroup);

        $destTables = $dbDest->listTables();
        $jobs = [];

        foreach ($map['tables'] as $legacyTable => $cfg) {
            $targetTable = $cfg['target'] ?? $legacyTable; // 1:1 default

            if (!empty($onlyList) && !in_array($legacyTable, $onlyList, true) && !in_array($targetTable, $onlyList, true)) {
                continue;
            }

            $exists = in_array($targetTable, $destTables, true);
            if ($exists) {
                // TODO: later handle ALTER/RENAME; for now skip existing tables
                CLI::write("Skip existing table: {$targetTable}", 'yellow');
                continue;
            }

            // Introspect source (legacy) table to build create definition
            [$fields, $indexes, $fks, $pk] = $this->introspectTable($dbSrc, $legacyTable);
            $jobs[] = [$legacyTable, $targetTable, $fields, $indexes, $fks, $pk];
        }

        if (empty($jobs)) {
            CLI::write('No tables to emit.', 'yellow');
            return EXIT_SUCCESS;
        }

        foreach ($jobs as [$legacyTable, $targetTable, $fields, $indexes, $fks, $pk]) {
            [$className, $fileName] = $this->makeMigrationName('Create_' . $targetTable);
            $up   = $this->renderCreateUp($targetTable, $fields, $indexes, $fks, $pk);
            $down = $this->renderDropDown($targetTable);

            if ($dryRun || !$force) {
                CLI::write("\n-- MIGRATION Table: {$legacyTable}(dry-run) --");
                CLI::write($up);
                CLI::write($down);
                continue;
            }

            // create file via make:migration to get proper header/path
            $nsEsc = str_replace('\\', '\\\\', $ns);
            $cmd   = sprintf('make:migration %s --namespace "%s" --table %s', $className, $nsEsc, $targetTable);
            CLI::write('Command: ' . $cmd, 'yellow');
            command($cmd);

            // try to locate last created migration path in namespace
            $path = $this->findLatestMigrationPath($ns, $className);
            if (!$path) {
                CLI::error('Could not locate created migration for ' . $className);
                continue;
            }

            // replace class body with UP/DOWN
            $this->replaceUpDown($path, $up, $down);
            CLI::write('Migration written: ' . $path, 'green');
        }

        return EXIT_SUCCESS;
    }

    // ---------- helpers ----------

    /** @return array{0:array,1:string,2:string} [map, source_group, target_group] */
    private function loadMapLite(string $path): array
    {
        if (!is_file($path)) throw new RuntimeException('map.yml not found: ' . $path);

        // Prefer ext/yaml for full fidelity
        if (function_exists('yaml_parse_file')) {
            $data = yaml_parse_file($path);
            if (!is_array($data)) throw new RuntimeException('Invalid YAML in ' . $path);
            $map = [
                'tables' => (array)($data['tables'] ?? []),
            ];
            $src = (string)($data['source_group'] ?? 'legacy');
            $dst = (string)($data['target_group'] ?? 'default');
            return [$map, $src, $dst];
        }

        // Minimal fallback parser: reads only top-level tables + tables[*].target
        $yaml = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $tables = [];
        $inTables = false; $current = null;
        $src = 'legacy'; $dst = 'default';
        foreach ($yaml as $line) {
            if (preg_match('/^source_group:\s*(\S+)/', $line, $m)) { $src = trim($m[1]); continue; }
            if (preg_match('/^target_group:\s*(\S+)/', $line, $m)) { $dst = trim($m[1]); continue; }
            if (preg_match('/^tables:\s*$/', $line)) { $inTables = true; continue; }
            if ($inTables) {
                if (preg_match('/^\s{2}([A-Za-z0-9_]+):\s*(\{\}|)$/', $line, $m)) {
                    $current = $m[1];
                    $tables[$current] = [];
                    continue;
                }
                if (preg_match('/^\s{4}target:\s*([A-Za-z0-9_]+)/', $line, $m)) {
                    if ($current) { $tables[$current]['target'] = $m[1]; }
                    continue;
                }
                if (preg_match('/^\S/', $line)) { // dedented → left tables block
                    $inTables = false; $current = null; continue;
                }
            }
        }
        return [['tables' => $tables], $src, $dst];
    }

    /** @return array{0:array,1:array,2:array,3:string} [fields,indexes,fks,primaryKey] */
    private function introspectTable($db, string $table): array
    {
        $fields = [];
        foreach ($db->getFieldData($table) as $f) {
            $def = [
                'name'        => $f->name,
                'type'        => strtoupper((string)$f->type),
                'max_length'  => $f->max_length,
                'nullable'    => (bool)($f->nullable ?? false),
                'default'     => $f->default ?? null,
                'primary_key' => (int)($f->primary_key ?? 0),
                'unsigned'    => (bool)($f->unsigned ?? false),
            ];
            $fields[$f->name] = $def;
        }

        $primaryKey = '';
        $indexes = [];
        foreach ($db->getIndexData($table) as $idx) {
            $indexes[] = [
                'name'   => $idx->name,
                'type'   => $idx->type, // PRIMARY|UNIQUE|INDEX
                'fields' => $idx->fields,
            ];
            if ($idx->type === 'PRIMARY' && !empty($idx->fields)) {
                $primaryKey = $idx->fields[0];
            }
        }

        // Foreign keys (best effort; may vary per driver)
        $fks = [];
        foreach ($db->getForeignKeyData($table) as $fk) {
            $fks[] = [
                'constraint_name'     => $fk->constraint_name,
                'column_name'         => $fk->column_name,
                'foreign_table_name'  => $fk->foreign_table_name,
                'foreign_column_name' => $fk->foreign_column_name,
                'on_delete'           => $fk->on_delete,
                'on_update'           => $fk->on_update,
            ];
        }

        return [$fields, $indexes, $fks, $primaryKey];
    }

    /** @return array{0:string,1:string} [className,fileNameCore] */
    private function makeMigrationName(string $base): array
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $base)));
        $time   = date('YmdHis');
        $class  = $studly; // CI4 will prefix file with timestamp automatically
        $file   = $time . '_' . $studly . '.php';
        return [$class, $file];
    }

    private function phpify($value): string
    {
        if (is_bool($value)) return $value ? 'true' : 'false';
        if ($value === null) return 'null';
        if (is_numeric($value)) return (string)$value;
        return "'" . str_replace("'", "\\'", (string)$value) . "'";
    }

    private function renderCreateUp(string $table, array $fields, array $indexes, array $fks, string $pk): string
    {
        $i = '        ';
        $up = '';
        $up .= $i . "\$this->forge->addField([\n";
        foreach ($fields as $def) {
            $up .= $i . "    '{$def['name']}' => [\n";
            $up .= $i . "        'type' => '{$def['type']}',\n";
            if (!empty($def['max_length'])) {
                $up .= $i . "        'constraint' => {$def['max_length']},\n";
            }
            if (!empty($def['unsigned'])) {
                $up .= $i . "        'unsigned' => true,\n";
            }
            if (!empty($def['nullable'])) {
                $up .= $i . "        'null' => true,\n";
            }
            if (array_key_exists('default', $def) && $def['default'] !== null && $def['default'] !== '') {
                $up .= $i . "        'default' => " . $this->phpify($def['default']) . ",\n";
            }
            if (!empty($def['primary_key'])) {
                $up .= $i . "        'auto_increment' => true,\n";
            }
            $up .= $i . "    ],\n";
        }
        $up .= $i . "]);\n\n";

        // Keys
        foreach ($indexes as $idx) {
            $fieldsArr = "['" . implode("', '", $idx['fields']) . "']";
            if ($idx['type'] === 'PRIMARY') {
                $up .= $i . "\$this->forge->addKey({$fieldsArr}, true);\n";
            } elseif ($idx['type'] === 'UNIQUE') {
                $up .= $i . "\$this->forge->addKey({$fieldsArr}, false, true, '{$idx['name']}');\n";
            } else {
                $up .= $i . "\$this->forge->addKey({$fieldsArr}, false, false, '{$idx['name']}');\n";
            }
        }
        $up .= "\n";

        // FKs (basic)
        foreach ($fks as $fk) {
            // single-column only for skeleton; extend to multi-col as needed
            $col  = $fk['column_name'][0] ?? $fk['column_name'];
            $rcol = $fk['foreign_column_name'][0] ?? $fk['foreign_column_name'];
            $up .= $i . "\$this->forge->addForeignKey('{$col}', '{$fk['foreign_table_name']}', '{$rcol}', '{$fk['on_delete']}', '{$fk['on_update']}', '{$fk['constraint_name']}');\n";
        }
        $up .= "\n";

        $up .= $i . "\$this->forge->createTable('{$table}');\n";

        return $up;
    }

    private function renderDropDown(string $table): string
    {
        $i = '        ';
        return $i . "\$this->forge->dropTable('{$table}');\n";
    }

    private function findLatestMigrationPath(string $ns, string $className): ?string
    {
        $runner = new \CodeIgniter\Database\MigrationRunner(config('Migrations'));
        $migs   = $runner->findNamespaceMigrations($ns);
        $candidates = array_values(array_filter($migs, static function ($m) use ($className) {
            return $m->name === $className;
        }));
        usort($candidates, static fn($a, $b) => strcmp($a->version, $b->version));
        $last = $candidates[count($candidates)-1] ?? null;
        return $last ? $last->path : null;
    }

    private function replaceUpDown(string $path, string $up, string $down): void
    {
        $code = file_get_contents($path);
        if ($code === false) throw new RuntimeException('Cannot read migration: ' . $path);
        $body = "    public function up()\n    {\n" . $up . "    }\n\n    public function down()\n    {\n" . $down . "    }\n";
        $new = preg_replace('/(<\\?php[\s\S]*class\s+\w+\s+extends\s+Migration\s*\{)[\s\S]*(\}\s*)$/', '$1' . "\n" . $body . "\n$2", $code);
        if ($new === null) throw new RuntimeException('Regex failed while replacing UP/DOWN in: ' . $path);
        file_put_contents($path, $new);
    }

    /** @return string[] */
    private function csvToArray(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn($v) => $v !== ''));
    }
}
