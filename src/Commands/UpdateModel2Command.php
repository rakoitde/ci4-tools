<?php
declare(strict_types=1);

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;

final class UpdateModelV2Command extends BaseCommand
{
    protected $group = 'Generators';
    protected $name = 'update:model2';
    protected $description = 'Create or update Model files from DB schema or map.yml (v2).';
    protected $usage = 'update:model2 <ModelName> [options]';

    /** @var array<string,string> */
    protected $arguments = [
        'name' => 'The model class name (without "Model" unless --suffix). Omit when using --from-map.',
    ];

    /** @var array<string,string> */
    protected $options = [
        '--namespace'   => 'Root namespace (default: App).',
        '--suffix'      => 'Append "Model" to the class name (User => UserModel).',
        '--group'       => 'DB group for target schema (default: default).',
        '--table'       => 'Explicit table name (otherwise taken from model or inferred).',
        '--from-map'    => 'Process all tables from app/Config/ETL/map.yml (path optional; default path used if omitted).',
        '--create-if-missing' => 'Generate model file when missing (uses make:model).',
        '--useSoftDeletes'    => 'true|false (default: keep current or false when creating)',
        '--useTimestamps'     => 'true|false (default: keep current or false when creating)',
        '--add-missing-timestamp-columns' => 'Add missing timestamp columns to DB (created/updated/deleted)',
        '--dry-run'    => 'Show planned changes without writing (default).',
        '--force'      => 'Write changes.',
    ];

    // --------- Entry ---------
    public function run(array $params): int
    {
        $ns            = (string) (CLI::getOption('namespace') ?? 'App');
        $suffix        = (bool)   (CLI::getOption('suffix') ?? false);
        $group         = (string) (CLI::getOption('group') ?? 'default');
        $tableOpt      = CLI::getOption('table');
        $fromMapOpt    = CLI::getOption('from-map');
        $createIfMissing = (bool) (CLI::getOption('create-if-missing') ?? false);
        $useSoftDeletesOpt = CLI::getOption('useSoftDeletes');
        $useTimestampsOpt  = CLI::getOption('useTimestamps');
        $addMissingTimestamps = (bool) (CLI::getOption('add-missing-timestamp-columns') ?? false);
        $dryRun        = (bool) (CLI::getOption('dry-run') ?? ! (CLI::getOption('force') ?? false));
        $force         = (bool) (CLI::getOption('force') ?? false);

        // Map-Pfad (Default)
        $mapPath = is_string($fromMapOpt) && $fromMapOpt !== ''
            ? $fromMapOpt
            : APPPATH . 'Config/ETL/map.yml';

        if ($fromMapOpt !== null) {
            // -------------- Batch: alle Models aus map.yml --------------
            $map = $this->loadMap($mapPath);
            $tables = $map['tables'] ?? [];
            if (!is_array($tables) || empty($tables)) {
                CLI::error('No tables found in map.yml (tables: ...).');
                return EXIT_ERROR;
            }
            $ok = true;
            foreach ($tables as $legacyTable => $cfg) {
                $targetTable = $cfg['target'] ?? $legacyTable; // 1:1 default
                $modelBase   = $this->guessModelNameFromTable($targetTable);
                $modelName   = $suffix ? $modelBase . 'Model' : $modelBase;
                $res = $this->processSingle($ns, $group, $modelName, $targetTable, $createIfMissing, $useSoftDeletesOpt, $useTimestampsOpt, $addMissingTimestamps, $dryRun, $force);
                $ok = $ok && ($res === EXIT_SUCCESS);
            }
            return $ok ? EXIT_SUCCESS : EXIT_ERROR;
        }

        // -------------- Single Model --------------
        if (empty($params[0]) && !$tableOpt) {
            CLI::error('Missing <ModelName> or --from-map / --table.');
            return EXIT_ERROR;
        }
        $modelName = $params[0] ?? $this->guessModelNameFromTable((string)$tableOpt);
        if ($suffix && substr($modelName, -5) !== 'Model') {
            $modelName .= 'Model';
        }

        $targetTable = is_string($tableOpt) ? $tableOpt : null;

        return $this->processSingle(
            $ns,
            $group,
            $modelName,
            $targetTable,
            $createIfMissing,
            $useSoftDeletesOpt,
            $useTimestampsOpt,
            $addMissingTimestamps,
            $dryRun,
            $force
        );
    }

    // --------- Core for one model ---------
    private function processSingle(
        string $ns,
        string $group,
        string $modelName,
        ?string $tableOpt,
        bool $createIfMissing,
        $useSoftDeletesOpt,
        $useTimestampsOpt,
        bool $addMissingTimestamps,
        bool $dryRun,
        bool $force
    ): int {
        $db = Database::connect($group);

        // Resolve file path via autoloader
        $basePath = $this->namespaceBasePath($ns);
        if (!$basePath) {
            CLI::error("Namespace '{$ns}' not found in autoloader.");
            return EXIT_ERROR;
        }
        $file = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . $modelName . '.php';

        // Ensure model exists or create
        if (!is_file($file)) {
            if (!$createIfMissing) {
                CLI::write("Model file missing: {$file}");
                CLI::error('Use --create-if-missing to generate with make:model.');
                return EXIT_ERROR;
            }
            $cmd = sprintf('make:model %s --namespace "%s" --suffix=%s --quiet', $modelName, $ns, (substr($modelName, -5) === 'Model' ? 'true' : 'false'));
            CLI::write('Command: ' . $cmd, 'yellow');
            command($cmd);
            if (!is_file($file)) {
                CLI::error('make:model did not create the file: ' . $file);
                return EXIT_ERROR;
            }
        }

        // Load current contents & reflect current model class to get its runtime config if available
        $contents = (string) file_get_contents($file);

        // Determine table
        $table = $tableOpt ?? $this->inferTableFromFile($contents) ?? $this->inferTableFromName($modelName);

        // Introspect DB
        $fields      = array_map(static fn($f) => $f->name, $db->getFieldData($table));
        $primaryKey  = $this->detectPrimaryKey($db, $table);
        $timestamps  = $this->detectTimestampFieldsFromModelFile($contents) + ['created_at','updated_at','deleted_at'];

        // allowedFields = all fields minus PK & timestamp/deleted fields
        $allowed = array_values(array_diff($fields, array_filter([
            $primaryKey,
            $timestamps['created'] ?? 'created_at',
            $timestamps['updated'] ?? 'updated_at',
            $timestamps['deleted'] ?? 'deleted_at',
        ])));

        // SoftDeletes / Timestamps target settings
        $targetSoftDel = $this->boolOptOrKeep($useSoftDeletesOpt, $this->readBoolProperty($contents, 'useSoftDeletes'));
        $targetTimestamps = $this->boolOptOrKeep($useTimestampsOpt,  $this->readBoolProperty($contents, 'useTimestamps'));

        // Optionally add missing timestamp columns to DB
        if ($addMissingTimestamps && $targetTimestamps) {
            $this->ensureTimestampColumns($db, $table, $timestamps);
        }

        // Prepare new content
        [$newContents, $changes] = $this->applyModelEdits($contents, [
            'table'         => $table,
            'primaryKey'    => $primaryKey,
            'allowedFields' => $allowed,
            'useSoftDeletes'=> $targetSoftDel,
            'useTimestamps' => $targetTimestamps,
            'createdField'  => $timestamps['created'] ?? 'created_at',
            'updatedField'  => $timestamps['updated'] ?? 'updated_at',
            'deletedField'  => $timestamps['deleted'] ?? 'deleted_at',
        ]);

        // Output
        CLI::write('');
        CLI::write('Model: ' . CLI::color($modelName, 'white') . '  (' . $ns . '\\Models)', 'yellow');
        CLI::write('  File         : ' . CLI::color($file, 'white'), 'green');
        CLI::write('  Table        : ' . CLI::color($table, 'white'), 'green');
        CLI::write('  PrimaryKey   : ' . CLI::color($primaryKey ?: '—', $primaryKey ? 'white' : 'red'), 'green');
        CLI::write('  AllowedFields: ' . CLI::color(implode(', ', $allowed), 'white'), 'green');
        CLI::write('  useSoftDeletes / useTimestamps: ' . CLI::color(json_encode($targetSoftDel) . ' / ' . json_encode($targetTimestamps), 'white'), 'green');

        if ($dryRun || !$force) {
            $this->printChanges($changes);
            CLI::write(CLI::color('Dry-run (no write). Use --force to apply.', 'yellow'));
            return EXIT_SUCCESS;
        }

        if ($newContents !== $contents) {
            file_put_contents($file, $newContents);
            CLI::write(CLI::color('Model updated.', 'green'));
        } else {
            CLI::write(CLI::color('No changes.', 'yellow'));
        }
        return EXIT_SUCCESS;
    }

    // --------- Helpers ---------

    private function namespaceBasePath(string $ns): ?string
    {
        $paths = service('autoloader')->getNamespace($ns);
        return $paths[0] ?? null;
    }

    private function inferTableFromName(string $modelName): string
    {
        $base = preg_replace('/Model$/', '', $modelName) ?? $modelName;
        // StudlyCase → snake_case plural heuristic (simple)
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base));
        // keine naive Pluralisierung; lass Tabellenname = snake
        return $snake;
    }

    private function inferTableFromFile(string $contents): ?string
    {
        if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $contents, $m)) {
            return $m[1];
        }
        return null;
    }

    /** @return array{created?:string,updated?:string,deleted?:string} */
    private function detectTimestampFieldsFromModelFile(string $contents): array
    {
        $out = [];
        if (preg_match('/protected\s+\$createdField\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $contents, $m)) $out['created'] = $m[1];
        if (preg_match('/protected\s+\$updatedField\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $contents, $m)) $out['updated'] = $m[1];
        if (preg_match('/protected\s+\$deletedField\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $contents, $m)) $out['deleted'] = $m[1];
        return $out;
    }

    private function boolOptOrKeep($opt, ?bool $current): bool
    {
        if (is_string($opt)) {
            return in_array(strtolower($opt), ['1','true','yes'], true);
        }
        if (is_bool($opt)) {
            return $opt;
        }
        return $current ?? false;
    }

    private function detectPrimaryKey($db, string $table): string
    {
        // prefer index metadata
        foreach ($db->getIndexData($table) as $idx) {
            if (($idx->type ?? '') === 'PRIMARY' && !empty($idx->fields[0])) {
                return $idx->fields[0];
            }
        }
        // fallback to field metadata
        foreach ($db->getFieldData($table) as $f) {
            if ((int)($f->primary_key ?? 0) === 1) {
                return $f->name;
            }
        }
        return '';
    }

    private function ensureTimestampColumns($db, string $table, array $tf): void
    {
        $names = array_map(static fn($f) => $f->name, $db->getFieldData($table));
        $missing = [];
        $map = [
            $tf['created'] ?? 'created_at' => ['type' => 'DATETIME', 'null' => true],
            $tf['updated'] ?? 'updated_at' => ['type' => 'DATETIME', 'null' => true],
            $tf['deleted'] ?? 'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        foreach ($map as $name => $def) {
            if (!in_array($name, $names, true)) {
                $missing[$name] = $def;
                CLI::write('    ALTER TABLE `' . $table . '` ADD `' . $name . '` DATETIME NULL', 'yellow');
            }
        }
        if ($missing) {
            $forge = \Config\Database::forge($db->DBGroup ?? null);
            $forge->addColumn($table, $missing);
        }
    }

    /**
     * @param array{
     *   table:string, primaryKey:string, allowedFields:array,
     *   useSoftDeletes:bool, useTimestamps:bool,
     *   createdField:string, updatedField:string, deletedField:string
     * } $target
     * @return array{0:string,1:array<int,string>} [newContents, changeLog]
     */
    private function applyModelEdits(string $contents, array $target): array
    {
        $changes = [];

        // ensure 'protected $table'
        [$contents, $changed] = $this->setOrInsertScalar($contents, 'table', $target['table']);
        if ($changed) $changes[] = "table => '{$target['table']}'";

        // primaryKey
        [$contents, $changed] = $this->setOrInsertScalar($contents, 'primaryKey', $target['primaryKey']);
        if ($changed) $changes[] = "primaryKey => '{$target['primaryKey']}'";

        // allowedFields
        [$contents, $changed] = $this->setOrInsertArray($contents, 'allowedFields', $target['allowedFields']);
        if ($changed) $changes[] = 'allowedFields => [' . implode(', ', $target['allowedFields']) . ']';

        // soft deletes / timestamps
        [$contents, $c1] = $this->setOrInsertBool($contents, 'useSoftDeletes', $target['useSoftDeletes']);
        [$contents, $c2] = $this->setOrInsertBool($contents, 'useTimestamps',  $target['useTimestamps']);
        if ($c1) $changes[] = 'useSoftDeletes => ' . json_encode($target['useSoftDeletes']);
        if ($c2) $changes[] = 'useTimestamps  => ' . json_encode($target['useTimestamps']);

        // timestamp field names
        [$contents, $c3] = $this->setOrInsertScalar($contents, 'createdField', $target['createdField']);
        [$contents, $c4] = $this->setOrInsertScalar($contents, 'updatedField', $target['updatedField']);
        [$contents, $c5] = $this->setOrInsertScalar($contents, 'deletedField', $target['deletedField']);
        if ($c3) $changes[] = "createdField => '{$target['createdField']}'";
        if ($c4) $changes[] = "updatedField => '{$target['updatedField']}'";
        if ($c5) $changes[] = "deletedField => '{$target['deletedField']}'";

        return [$contents, $changes];
    }

    /** scalar like protected $table = 'x'; */
    private function setOrInsertScalar(string $code, string $prop, string $value): array
    {
        $pattern = '/protected\s+\$' . preg_quote($prop, '/') . '\s*=\s*[\'"]([^\'"]*)[\'"]\s*;/';
        if (preg_match($pattern, $code)) {
            $new = preg_replace($pattern, "protected \$$prop = '" . addslashes($value) . "';", $code);
            return [$new, $new !== $code];
        }
        // insert after class opening
        $insert = "    protected \$$prop = '" . addslashes($value) . "';\n";
        $new = preg_replace('/(class\s+[^{]+{\s*\n)/', "$1$insert", $code, 1, $count);
        if ($count === 0) $new = $code . "\n" . $insert;
        return [$new, true];
    }

    /** boolean like protected $useTimestamps = true; */
    private function setOrInsertBool(string $code, string $prop, bool $value): array
    {
        $pattern = '/protected\s+\$' . preg_quote($prop, '/') . '\s*=\s*(true|false)\s*;/';
        if (preg_match($pattern, $code)) {
            $new = preg_replace($pattern, "protected \$$prop = " . ($value ? 'true' : 'false') . ';', $code);
            return [$new, $new !== $code];
        }
        $insert = '    protected $' . $prop . ' = ' . ($value ? 'true' : 'false') . ";\n";
        $new = preg_replace('/(class\s+[^{]+{\s*\n)/', "$1$insert", $code, 1, $count);
        if ($count === 0) $new = $code . "\n" . $insert;
        return [$new, true];
    }

    /** array like protected $allowedFields = ['a','b']; */
    private function setOrInsertArray(string $code, string $prop, array $values): array
    {
        $normalized = array_values(array_unique($values));
        $body = "'" . implode("', '", array_map(static fn($v) => addslashes((string)$v), $normalized)) . "'";
        $pattern = '/protected\s+\$' . preg_quote($prop, '/') . '\s*=\s*\[(.*?)\]\s*;/s';
        if (preg_match($pattern, $code)) {
            $new = preg_replace($pattern, "protected \$$prop = [$body];", $code);
            return [$new, $new !== $code];
        }
        $insert = "    protected \$$prop = [$body];\n";
        $new = preg_replace('/(class\s+[^{]+{\s*\n)/', "$1$insert", $code, 1, $count);
        if ($count === 0) $new = $code . "\n" . $insert;
        return [$new, true];
    }

    private function printChanges(array $changes): void
    {
        if (empty($changes)) {
            CLI::write('No changes needed.', 'yellow');
            return;
        }
        CLI::write('Planned changes:', 'yellow');
        foreach ($changes as $c) {
            CLI::write('  - ' . $c, 'green');
        }
    }

    // ---------- Map loader ----------
    /** @return array<string,mixed> */
    private function loadMap(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Map not found: ' . $path);
        }
        // Minimal YAML loader (Symfony/Yaml wäre nice, hier bewusst einfach):
        $yaml = file_get_contents($path);
        if ($yaml === false) throw new RuntimeException('Cannot read map: ' . $path);
        // Wenn ext/yaml vorhanden, nutzen. Sonst sehr simple Parser (nur für keys/strings).
        if (function_exists('yaml_parse')) {
            $data = yaml_parse($yaml);
            return is_array($data) ? $data : [];
        }
        // Fallback Note: Für produktiv unbedingt proper YAML Parser verwenden.
        // Hier nur Dummy – erwarte map:init/schema:emit liefern gültige Struktur.
        return [];
    }

    private function guessModelNameFromTable(string $table): string
    {
        // contract_items -> ContractItems
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $table)));
        return $studly;
    }
}
