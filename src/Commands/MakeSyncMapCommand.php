<?php

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * make:syncmap
 *
 * Generate or update app/Database/Sync/SyncMap.php from the legacy database schema.
 *
 * Behavior:
 *  - If SyncMap.php exists:
 *      * Instantiates the class and reads $tables (optional) and $table (existing mappings).
 *      * Only missing legacy tables are appended as new mappings.
 *      * --force rewrites the file from scratch (all selected tables).
 *      * --dry-run prints the changes only.
 *  - If not existing:
 *      * Creates the file with constructor filling $this->table using Table::make(...) & Column::make(...).
 *
 * Options:
 *   --group       Source DB group (default: legacy)
 *   --class       FQCN of the map class (default: App\Database\Sync\SyncMap)
 *   --out         Output path (default: APPPATH . 'Database/Sync/SyncMap.php')
 *   --force       Overwrite entire file with freshly generated content
 *   --dry-run     Show what would be written/appended without touching the file
 */
final class MakeSyncMapCommand extends BaseCommand
{
    protected $group = 'legacy';
    protected $name = 'make:syncmap';
    protected $description = 'Create or update app/Database/Sync/SyncMap.php from legacy DB schema.';
    protected $usage = 'make:syncmap [--group legacy] [--class "App\\Database\\Sync\\SyncMap"] [--out app/Database/Sync/SyncMap.php] [--table users] [--force] [--dry-run]';

    /** @var array<string,string> */
    protected $options = [
        '--group'   => 'Source DB group (default: legacy).',
        '--class'   => 'FQCN of the SyncMap class (default: App\\Database\\Sync\\SyncMap).',
        '--out'     => 'Path of SyncMap.php (default: app/Database/Sync/SyncMap.php).',
        '--table'   => 'Only process this single table (ignores others).',
        '--force'   => 'Rewrite file entirely.',
        '--dry-run' => 'Print planned changes only.',
    ];

    public function run(array $params): int
    {
        $group   = (string) (CLI::getOption('group') ?? 'legacy');
        $fqcn    = (string) (CLI::getOption('class') ?? 'App\\Database\\Sync\\SyncMap');
        $outPath = (string) (CLI::getOption('out')   ?? APPPATH . 'Database/Sync/SyncMap.php');
        $force   = (bool)   (CLI::getOption('force') ?? false);
        $dryRun  = (bool)   (CLI::getOption('dry-run') ?? false);
        $onlyOne = (string) (CLI::getOption('table') ?? '');


        $db = Database::connect($group);
        if (!$db instanceof BaseConnection) {
            throw new RuntimeException('Cannot connect DB group: ' . $group);
        }

        $allLegacyTables = $db->listTables();
        sort($allLegacyTables);

        if ($onlyOne !== '' && !in_array($onlyOne, $allLegacyTables, true)) {
            CLI::error("Table '{$onlyOne}' not found in legacy database.");
            return EXIT_ERROR;
        }

        $existingClassLoaded = class_exists('\\' . ltrim($fqcn, '\\'));
        $fileExists          = is_file($outPath);

        if ($fileExists && !$existingClassLoaded) {
            require_once $outPath;
            $existingClassLoaded = class_exists('\\' . ltrim($fqcn, '\\'));
        }

        $selectedTables = $onlyOne !== '' ? [$onlyOne] : $allLegacyTables;
        $existingMap    = null;
        $existingTablesProp = [];

        if ($existingClassLoaded) {
            $existingMap = new $fqcn();
            $existingTablesProp = (array) ($existingMap->tables ?? []);
            if (!empty($existingTablesProp) && $onlyOne === '') {
                $selectedTables = array_values(array_intersect($allLegacyTables, $existingTablesProp));
            }
        }

        $alreadyMapped = [];
        if ($existingMap && is_array($existingMap->table ?? null)) {
            foreach ($existingMap->table as $t) {
                $src = $t->source ?? null;
                $tgt = $t->target ?? null;
                if ($src) {
                    $alreadyMapped[$src] = true;
                } elseif ($tgt) {
                    $alreadyMapped[$tgt] = true;
                }
            }
        }

        $toGenerate = $force
            ? $selectedTables
            : array_values(array_filter($selectedTables, static fn($t) => !isset($alreadyMapped[$t])));

        if (empty($toGenerate) && $fileExists && !$force) {
            CLI::write('Nothing to add — all selected tables already present.', 'yellow');
            return EXIT_SUCCESS;
        }

        $introspected = [];
        foreach ($toGenerate as $legacyTable) {
            [$fields, $primaryKey] = $this->introspectFieldsAndPk($db, $legacyTable);
            $introspected[] = [
                'legacy' => $legacyTable,
                'target' => $this->defaultTargetName($legacyTable),
                'pk'     => $primaryKey ?: 'id',
                'fields' => $fields,
            ];
        }

        $generatedBlocks = array_map(
            fn($it) => $this->renderTableMake($it['legacy'], $it['target'], $it['pk'], $it['fields']),
            $introspected
        );
        $generatedCode   = implode(",\n\n", $generatedBlocks);

        if ($dryRun) {
            if ($force || !$fileExists) {
                CLI::write("\n-- SyncMap.php (full content preview) --\n");
                CLI::write($this->renderFullClass($fqcn, $generatedCode));
            } else {
                CLI::write("\n-- Append block preview (to be merged into __construct) --\n");
                CLI::write($this->renderAppendBlock($generatedCode));
            }
            return EXIT_SUCCESS;
        }

        if (!$fileExists || $force) {
            $dir = \dirname($outPath);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create directory: ' . $dir);
            }
            $code = $this->renderFullClass($fqcn, $generatedCode, $existingTablesProp);
            file_put_contents($outPath, $code);
            CLI::write(($fileExists ? 'Rewrote' : 'Created') . ': ' . $outPath, 'green');
            return EXIT_SUCCESS;
        }

        $src = file_get_contents($outPath);
        if ($src === false) {
            throw new RuntimeException('Cannot read file: ' . $outPath);
        }

        $appendBlock = $this->renderAppendBlock($generatedCode);
        $updated     = $this->injectIntoConstructor($src, $appendBlock);

        if ($updated === null) {
            $updated = $this->injectConstructor($src, $appendBlock);
        }

        if ($updated === null) {
            throw new RuntimeException('Cannot update SyncMap.php — constructor injection failed.');
        }

        file_put_contents($outPath, $updated);
        CLI::write('Appended new mappings into: ' . $outPath, 'green');
        return EXIT_SUCCESS;
    }

    // ---------- helpers ----------

    /** @return array{0:string[],1:?string} [fields, primaryKey] */
    private function introspectFieldsAndPk(BaseConnection $db, string $table): array
    {
        $cols = $db->getFieldData($table);
        $fields = [];
        foreach ($cols as $f) {
            $fields[] = $f->name;
        }

        $primaryKey = null;
        foreach ($db->getIndexData($table) as $idx) {
            if ($idx->type === 'PRIMARY' && !empty($idx->fields)) {
                $primaryKey = $idx->fields[0];
                break;
            }
        }

        return [$fields, $primaryKey];
    }

    private function defaultTargetName(string $legacy): string
    {
        // naive singularization: lower + trim prefix tbl_
        $t = strtolower($legacy);
        if (str_starts_with($t, 'tbl')) {
            $t = ltrim(substr($t, 3), '_');
        }
        return $t;
    }

    private function renderTableMake(string $legacy, string $target, string $pk, array $fields): string
    {
        $cols = array_map(
            fn($c) => "                Column::make('{$c}', '{$c}')",
            $fields
        );
        $colsBlock = implode(",\n", $cols);

        $code = <<<PHP
        Table::make('{$legacy}')
            ->target('{$target}')
            ->syncMode('full')
            ->batchSize(500)
            ->primaryKey('{$pk}')
            ->preservePrimaryKey(false)
            ->columns(
        {$colsBlock}
            )
        PHP;

        return $code;
    }

    private function renderFullClass(string $fqcn, string $tablesCode, array $tablesAllowlist = []): string
    {
        $ns  = trim(substr($fqcn, 0, strrpos($fqcn, '\\')));
        $cls = substr($fqcn, strrpos($fqcn, '\\') + 1);

        $allow = '';
        if (!empty($tablesAllowlist)) {
            $arr = implode("','", array_map('strval', $tablesAllowlist));
            $allow = "        \$this->tables = ['{$arr}'];\n\n";
        }

        return <<<PHP
        <?php
        namespace {$ns};

        use CodeIgniter\Config\BaseConfig;
        use Rakoitde\\Tools\\Domain\\Table;
        use Rakoitde\\Tools\\Domain\\Column;

        class {$cls} extends BaseConfig
        {
            /** Optional allowlist of legacy tables (used by make:syncmap); ignored by sync command */
            public array \$tables = [];

            /** Effective mappings used by sync/migration commands */
            public array \$table = [];

            public function __construct()
            {
                parent::__construct();

        {$allow}
                // Initial mappings (generated by make:syncmap)
                \$this->table = [
        {$tablesCode}
                ];
            }
        }
        PHP;
    }

    private function renderAppendBlock(string $tablesCode): string
    {
        return <<<PHP

        // >>> GENERATED by make:syncmap <<<
        \$this->table = array_merge(\$this->table, [
        {$tablesCode}
        ]);
        PHP;
    }

    /**
     * Injects $block before the closing brace of the first constructor found.
     * Returns updated code or null if not found.
     */
    private function injectIntoConstructor(string $src, string $block): ?string
    {
        $pattern = '/(function\s+__construct\s*\(\s*\)\s*\{)(.*?)(\n\s*\})/si';
        $updated = preg_replace_callback($pattern, function ($m) use ($block) {
            $body = rtrim($m[2]);
            $injected = $body . "\n" . $block . "\n";
            return $m[1] . $injected . $m[3];
        }, $src, 1);

        return $updated === null ? null : $updated;
    }

    /**
     * Adds a minimal constructor to the class and inserts the block inside it.
     * Returns updated code or null if class body not found.
     */
    private function injectConstructor(string $src, string $block): ?string
    {
        $pattern = '/class\s+\w+\s+extends\s+BaseConfig\s*\{(.*)\}\s*$/si';
        if (!preg_match($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $constructor = <<<PHP

        public function __construct()
        {
            parent::__construct();
        {$block}
        }

        PHP;

        // Insert constructor after class opening brace
        $pos = strpos($src, '{', strpos($src, 'class '));
        if ($pos === false) {
            return null;
        }
        $pos++; // after '{'
        return substr($src, 0, $pos) . $constructor . substr($src, $pos);
    }
}
