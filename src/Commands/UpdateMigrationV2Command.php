<?php
declare(strict_types=1);

namespace Rakoitde\Tools\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\MigrationRunner;
use Rakoitde\Tools\Migrations\MigrationFileEditor;
use Rakoitde\Tools\Snapshot\SnapshotDiffService;
use Rakoitde\Tools\Forge\ForgeChangeBuilder;
use RuntimeException;

/**
 * New command that builds on the helper classes and can live next to the legacy one.
 *
 * Modes:
 *  - create-first : Creates (or updates) the first migration with a full createTable()
 *  - alter-last   : Alters the last migration file in-place using a diff vs. last snapshot
 *  - alter-new    : Creates a new migration and writes only ALTER statements from diff
 *
 * Options:
 *  --namespace                Root namespace (default: App)
 *  --suffix                   Append "Model" to the provided name when resolving the model
 *  --mode                     create-first|alter-last|alter-new
 *  --disableForeignKeyChecks  Wrap UP with disable/enable FK checks
 *  --dry-run                  Print planned UP/DOWN without writing files
 *  --yes                      Run non-interactively (assume defaults if needed)
 *  --rename                   One or more renames, e.g. "old:new" or comma-separated: "a:b,c:d";
 *                              can also be passed multiple times (e.g. --rename x:y --rename a:b)
 */
final class UpdateMigrationV2Command extends BaseCommand
{
    protected $group = 'Generators';
    protected $name = 'update:migration2';
    protected $description = 'Updates migrations using snapshot diff (v2).';
    protected $usage = 'update:migration2 <ModelName> [options]';

    /** @var array<string,string> */
    protected $arguments = [
        'name' => 'The model class name (without "Model" unless --suffix).',
    ];

    /** @var array<string,string> */
    protected $options = [
        '--namespace'               => 'Set root namespace. Default: "App".',
        '--suffix'                  => 'Append "Model" when resolving the class.',
        '--mode'                    => 'create-first|alter-last|alter-new',
        '--disableForeignKeyChecks' => 'Temporarily bypass FK checks in UP()',
        '--dry-run'                 => 'Show changes without writing files',
        '--yes'                     => 'Skip prompts; assume defaults',
        '--rename'                  => 'One or more renames: "old:new" or comma-separated "a:b,c:d"; can be repeated.',
        '--auto-rename'             => 'Enable heuristic rename detection (default: off)',
        '--no-auto-rename'          => 'Explicitly disable heuristic rename detection',
    ];

    public function run(array $params): int
    {
        if (empty($params[0])) {
            CLI::error('Missing required argument: <ModelName>');
            return EXIT_ERROR;
        }

        $rootNamespace = (string) (CLI::getOption('namespace') ?? 'App');
        $appendModel   = (bool) (CLI::getOption('suffix') ?? false);
        $mode          = (string) (CLI::getOption('mode') ?? '');
        $nonInteractive= (bool) (CLI::getOption('yes') ?? false);
        $disableFK     = (bool) (CLI::getOption('disableForeignKeyChecks') ?? false);
        $dryRun        = (bool) (CLI::getOption('dry-run') ?? false);
        $manualRenames = $this->parseRenameOptions();
        $autoRename = (bool) (CLI::getOption('auto-rename') ?? false);
        if ((bool) (CLI::getOption('no-auto-rename') ?? false)) {
            $autoRename = false; // expliziter Override
        }

        $baseName  = $params[0];
        $modelName = $baseName . ($appendModel ? 'Model' : '');
        $model     = model($modelName);
        if ($model === null) {
            CLI::error("Model {$modelName} not found.");
            return EXIT_ERROR;
        }

        $runner = new MigrationRunner(config('Migrations'));
        $migs   = $runner->findNamespaceMigrations($rootNamespace);
        $migPrefix = $baseName . ($appendModel ? 'Migration' : '');
        $matching = array_values(array_filter($migs, static function ($m) use ($migPrefix) {
            return str_starts_with($m->name, $migPrefix);
        }));
        usort($matching, static fn($a, $b) => strcmp($a->version, $b->version));

        $first = $matching[0] ?? null;
        $last  = $matching[count($matching)-1] ?? null;

        if ($mode === '') {
            $mode = $this->chooseMode($matching, $nonInteractive);
            if ($mode === '') {
                return EXIT_SUCCESS; // aborted
            }
        }

        $editor  = new MigrationFileEditor();
        $diffSvc = new SnapshotDiffService();
        $builder = new ForgeChangeBuilder(disableFK: $disableFK);

        // Build the current snapshot from DB
        $current = $this->createSnapshotArray($model->db, $model->table);

        // try {
            if ($mode === 'create-first') {

                [$up, $down] = $builder->buildCreate($model->table, array_values($current['fields']), array_values($current['indexes']), array_values($current['foreignkeys']));

                if ($dryRun) {
                    $this->printPlan($up, $down, []); return EXIT_SUCCESS;
                }

                if ($first === null) {
                    $this->createMigrationFile($migPrefix, $rootNamespace, $model->table);
                    [$first, $last] = $this->refreshMigrations($runner, $rootNamespace, $migPrefix);
                }

                $editor->replaceUpDown($first->path, $up, $down);
                $this->writeSnapshotJson($first->path, $current);
                CLI::write('Updated first migration with createTable().');
                return EXIT_SUCCESS;
            }

            if ($mode === 'alter-last') {
                if ($last === null) {
                    throw new RuntimeException('No existing migration found to alter. Use --mode=create-first or --mode=alter-new.');
                }
                $old = $this->readSnapshotJsonOrFail($last->path);

                // 1) Detect renames (manual first, then heuristic)
                [$renamed, $oldAfter, $currentAfter] = $this->detectRenames($old, $current, $manualRenames, $autoRename);

                // 2) Build diff on remaining add/modify/drop
                $diff = $diffSvc->diff($oldAfter, $currentAfter);
                // strip any accidental duplicates
                foreach ($renamed as $r) {
                    unset($diff['addFields'][$r['to']]);
                    $diff['dropFields'] = array_values(array_filter($diff['dropFields'], static fn($n) => $n !== $r['from']));
                }

                // 3) Build rename snippets + builder snippets
                [$renameUp, $renameDown] = $this->buildRenameSnippets($model->table, $renamed);
                [$up, $down] = $builder->buildAlter($diff);
                $up = $renameUp . $up;
                $down = $down . $renameDown; // invert order for down

                if ($dryRun) {
                    $this->printPlan($up, $down, $renamed); return EXIT_SUCCESS;
                }

                $editor->replaceUpDown($last->path, $up, $down);
                $this->writeSnapshotJson($last->path, $current);
                CLI::write('Altered last migration.');
                return EXIT_SUCCESS;
            }

            if ($mode === 'alter-new') {
                $old = $last ? $this->readSnapshotJsonOrFail($last->path) : ['table' => $model->table, 'fields'=>[], 'indexes'=>[], 'foreignkeys'=>[]];

                // 1) Detect renames
                //extract($this->detectRenames($old, $current, $manualRenames));
                [$renamed, $oldAfter, $currentAfter] = $this->detectRenames($old, $current, $manualRenames, $autoRename);

                if ($last === null) {
                    [$renameUp, $renameDown] = ['', '']; // no baseline → full create
                    [$up, $down] = $builder->buildCreate($model->table, array_values($current['fields']), array_values($current['indexes']), array_values($current['foreignkeys']));
                } else {
                    $diff = $diffSvc->diff($oldAfter, $currentAfter);
                    foreach ($renamed as $r) {
                        unset($diff['addFields'][$r['to']]);
                        $diff['dropFields'] = array_values(array_filter($diff['dropFields'], static fn($n) => $n !== $r['from']));
                    }
                    [$renameUp, $renameDown] = $this->buildRenameSnippets($model->table, $renamed);
                    [$up, $down] = $builder->buildAlter($diff);
                    $up = $renameUp . $up;
                    $down = $down . $renameDown;
                }

                if ($dryRun) {
                    $this->printPlan($up, $down, $renamed); return EXIT_SUCCESS;
                }

                $this->createMigrationFile($migPrefix, $rootNamespace, $model->table);
                [$first, $last] = $this->refreshMigrations($runner, $rootNamespace, $migPrefix);
                $target = $last; // newest file

                $editor->replaceUpDown($target->path, $up, $down);
                $this->writeSnapshotJson($target->path, $current);
                CLI::write('Created new migration with ALTER statements (incl. renames).');
                return EXIT_SUCCESS;
            }

            throw new RuntimeException("Unknown mode: {$mode}");
        // } catch (\Throwable $e) {
        //     CLI::error($e->getMessage());
        //     return EXIT_ERROR;
        // }
    }

    private function chooseMode(array $matching, bool $nonInteractive): string
    {
        if ($nonInteractive) {
            return empty($matching) ? 'create-first' : 'alter-new';
        }

        $count = count($matching);
        CLI::write('');
        CLI::write(CLI::color((string)$count, 'green') . ' migration files found.', 'white');
        CLI::write(' [0] ' . CLI::color('abort', 'green'), 'green');
        if ($count === 0) {
            CLI::write(' [1] ' . CLI::color('create-first: create table', 'white'), 'green');
        } elseif ($count === 1) {
            CLI::write(' [1] ' . CLI::color('create-first: update 1st with create table', 'white'), 'green');
            CLI::write(' [3] ' . CLI::color('alter-new: create new file with alter table', 'white'), 'green');
        } else {
            CLI::write(' [1] ' . CLI::color('create-first: update 1st with create table (drop rest manual)', 'white'), 'green');
            CLI::write(' [2] ' . CLI::color('alter-last: update last file with alter table', 'white'), 'green');
            CLI::write(' [3] ' . CLI::color('alter-new: create new file with alter table', 'white'), 'green');
        }
        CLI::write('');
        $choices = ['0','1','2','3'];
        do {
            $choice = trim(CLI::input('Make your choice ' . CLI::color('[0]', 'green') . ': '));
        } while ($choice !== '' && !in_array($choice, $choices, true));

        return match ($choice) {
            '0','' => '',
            '1'    => 'create-first',
            '2'    => 'alter-last',
            '3'    => 'alter-new',
            default=> '',
        };
    }

    /**
     * Parses --rename options from CLI. Supports multiple forms:
     * - --rename old:new (can be passed multiple times)
     * - --rename=old:new
     * - --rename old1:new1,old2:new2
     * @return array<int,array{from:string,to:string}>
     */
    private function parseRenameOptions(): array
    {
        $pairs = [];
        $opt = CLI::getOption('rename');
        if (is_string($opt)) {
            $pairs = array_merge($pairs, $this->explodeRenameString($opt));
        }
        // Also scan argv to catch repeated --rename occurrences
        $argv = $GLOBALS['argv'] ?? [];
        for ($i=0; $i<count($argv); $i++) {
            $arg = $argv[$i];
            if ($arg === '--rename' && isset($argv[$i+1])) {
                $pairs = array_merge($pairs, $this->explodeRenameString($argv[$i+1]));
                $i++; continue;
            }
            if (str_starts_with($arg, '--rename=')) {
                $pairs = array_merge($pairs, $this->explodeRenameString(substr($arg, 9)));
            }
        }
        // normalize & dedupe
        $out = [];
        foreach ($pairs as $p) {
            $from = trim($p['from']); $to = trim($p['to']);
            if ($from !== '' && $to !== '' && $from !== $to) {
                $out[$from] = ['from'=>$from,'to'=>$to]; // last wins if duplicate
            }
        }
        return array_values($out);
    }

    /** @return array<int,array{from:string,to:string}> */
    private function explodeRenameString(string $value): array
    {
        $out = [];
        $chunks = preg_split('/[\s,;]+/', trim($value)) ?: [];
        foreach ($chunks as $chunk) {
            if ($chunk === '') continue;
            $parts = explode(':', $chunk, 2);
            if (count($parts) === 2) {
                $out[] = ['from'=>$parts[0], 'to'=>$parts[1]];
            }
        }
        return $out;
    }

    /**
     * Detect renames from old->current snapshots using manual mappings first,
     * then (optional) heuristic matching.
     * Returns [renamed[], oldAfter, currentAfter].
     * Each rename item contains oldDef & newDef for correct UP/DOWN.
     *
     * @param array $manualRenames [ ['from'=>old,'to'=>new], ... ]
     * @return array{0:array<int,array{from:string,to:string,oldDef:array,newDef:array}>,1:array,2:array}
     */
    private function detectRenames(array $old, array $current, array $manualRenames, bool $autoRename): array
    {
        $oldF = $old['fields'] ?? [];
        $newF = $current['fields'] ?? [];

        $dropped = array_values(array_diff(array_keys($oldF), array_keys($newF)));
        $added   = array_values(array_diff(array_keys($newF), array_keys($oldF)));

        $renamed = [];

        // 1) manuelle Renames (höchste Priorität)
        foreach ($manualRenames as $m) {
            $from = $m['from']; $to = $m['to'];
            if (isset($oldF[$from], $newF[$to])) {
                $renamed[] = ['from'=>$from, 'to'=>$to, 'oldDef'=>$oldF[$from], 'newDef'=>$newF[$to]];
                $dropped = array_values(array_filter($dropped, static fn($n) => $n !== $from));
                $added   = array_values(array_filter($added, static fn($n) => $n !== $to));
            }
        }

        // 2) optionale Heuristik
        if ($autoRename && !empty($dropped) && !empty($added)) {
            $threshold = 5; // konservativ (max ~6)
            $pairs = [];
            $bestMap = [];
            $bestRev = [];

            foreach ($dropped as $from) {
                $best = ['to'=>null,'score'=>-1];
                foreach ($added as $to) {
                    $score = $this->fieldSimilarity($oldF[$from], $newF[$to]);
                    if ($score > $best['score']) $best = ['to'=>$to,'score'=>$score];
                    $pairs["$from=>$to"] = $score;
                }
                $bestMap[$from] = $best;
            }

            foreach ($added as $to) {
                $bestTo = ['from'=>null,'score'=>-1];
                foreach ($dropped as $from) {
                    $score = $pairs["$from=>$to"] ?? $this->fieldSimilarity($oldF[$from], $newF[$to]);
                    if ($score > $bestTo['score']) $bestTo = ['from'=>$from,'score'=>$score];
                }
                $bestRev[$to] = $bestTo;
            }

            foreach ($dropped as $from) {
                $cand = $bestMap[$from] ?? null;
                if (!$cand || $cand['to'] === null) continue;
                $to = $cand['to']; $score = (int)$cand['score'];
                $rev = $bestRev[$to] ?? null;
                if ($rev && $rev['from'] === $from && $score >= $threshold) {
                    $renamed[] = ['from'=>$from,'to'=>$to,'oldDef'=>$oldF[$from],'newDef'=>$newF[$to]];
                    $added   = array_values(array_filter($added, static fn($n) => $n !== $to));
                    $dropped = array_values(array_filter($dropped, static fn($n) => $n !== $from));
                }
            }
        }

        // 3) Snapshots für Diff bereinigen
        $oldAfter = $old;
        $currentAfter = $current;
        foreach ($renamed as $r) {
            unset($oldAfter['fields'][$r['from']]);
            unset($currentAfter['fields'][$r['to']]);
        }

        return [$renamed, $oldAfter, $currentAfter];
    }


    /** similarity score between two field defs (0..6+) */
    private function fieldSimilarity(array $a, array $b): int
    {
        $score = 0;
        if (($a['type'] ?? null) === ($b['type'] ?? null)) $score += 2;
        if (($a['max_length'] ?? null) === ($b['max_length'] ?? null)) $score += 1;
        if ((bool)($a['nullable'] ?? false) === (bool)($b['nullable'] ?? false)) $score += 1;
        if (($a['default'] ?? null) === ($b['default'] ?? null)) $score += 1;
        if ((int)($a['primary_key'] ?? 0) === (int)($b['primary_key'] ?? 0)) $score += 1;
        return $score;
    }

    /**
     * Build rename snippets (UP uses newDef, DOWN uses oldDef)
     * @param array<int,array{from:string,to:string,oldDef:array,newDef:array}> $renamed
     * @return array{0:string,1:string}
     */
    private function buildRenameSnippets(string $table, array $renamed): array
    {
        if (empty($renamed)) return ['', ''];
        $up=''; $down='';
        foreach ($renamed as $r) {
            $old = $r['oldDef'];
            $new = $r['newDef'];
            $up  .= $this->renderModifyColumn($table, $r['from'], $r['to'], $new);
            $down.= $this->renderModifyColumn($table, $r['to'], $r['from'], $old);
        }
        return [$up, $down];
    }

    /** Render a single modifyColumn snippet for rename with definition $def */
    private function renderModifyColumn(string $table, string $from, string $to, array $def): string
    {
        $type = $def['type'] ?? 'VARCHAR';
        $s = "        \$this->forge->modifyColumn('{$table}', [ '{$from}' => [ 'name' => '{$to}', 'type' => '{$type}'";
        if (!empty($def['max_length'])) $s .= ", 'constraint' => {$def['max_length']}";
        if (!empty($def['nullable']))   $s .= ", 'null' => true";
        if (array_key_exists('default', $def) && $def['default'] !== null && $def['default'] !== '') {
            $d = is_numeric($def['default']) ? (string)$def['default'] : ("'" . str_replace("'","\\'", (string)$def['default']) . "'");
            $s .= ", 'default' => {$d}";
        }
        if (!empty($def['primary_key'])) $s .= ", 'auto_increment' => true";
        $s .= " ] ]);\n";
        return $s;
    }

    /**
     * Creates a new migration via make:migration and returns the created PHP path.
     */
    private function createMigrationFile(string $migPrefix, string $namespace, string $table): string
    {
        $nsEsc = str_replace('\\', '\\\\', $namespace);
        $name  = $migPrefix . date('His'); // ensure uniqueness if multiple in same second
        $cmd   = sprintf('make:migration %s --namespace "%s" --table %s', $name, $nsEsc, $table);
        CLI::write('Command: ' . $cmd);
        command($cmd);
        return $name; // not used by caller—kept for clarity
    }

    /**
     * Re-scan migrations for the given prefix after creating one.
     * @return array{0:object|null,1:object|null} [first,last]
     */
    private function refreshMigrations(MigrationRunner $runner, string $namespace, string $prefix): array
    {
        $all = $runner->findNamespaceMigrations($namespace);
        $matching = array_values(array_filter($all, static fn($m) => str_starts_with($m->name, $prefix)));
        usort($matching, static fn($a,$b) => strcmp($a->version, $b->version));
        return [$matching[0] ?? null, $matching[count($matching)-1] ?? null];
    }

    /**
     * Build snapshot from DB connection & table.
     * Structure matches SnapshotDiffService expectations.
     */
    private function createSnapshotArray($db, string $table): array
    {
        $fields = [];
        foreach ($db->getFieldData($table) as $f) {
            $fields[$f->name] = [
                'name'        => $f->name,
                'type'        => $f->type,
                'max_length'  => $f->max_length,
                'nullable'    => (bool)($f->nullable ?? false),
                'default'     => $f->default ?? null,
                'primary_key' => (int)($f->primary_key ?? 0),
            ];
        }

        $indexes = [];
        foreach ($db->getIndexData($table) as $idx) {
            $indexes[$idx->name] = [
                'name'   => $idx->name,
                'type'   => $idx->type,
                'fields' => $idx->fields,
            ];
        }

        $foreignkeys = [];
        foreach ($db->getForeignKeyData($table) as $fk) {
            $foreignkeys[$fk->constraint_name] = [
                'constraint_name'     => $fk->constraint_name,
                'column_name'         => $fk->column_name,
                'foreign_table_name'  => $fk->foreign_table_name,
                'foreign_column_name' => $fk->foreign_column_name,
                'on_delete'           => $fk->on_delete,
                'on_update'           => $fk->on_update,
            ];
        }

        return [
            'table'       => $table,
            'fields'      => $fields,
            'indexes'     => $indexes,
            'foreignkeys' => $foreignkeys,
        ];
    }

    private function snapshotPathFromPhp(string $phpPath): string
    {
        return preg_replace('/\.php$/', '.json', $phpPath) ?? ($phpPath . '.json');
    }

    /** @return array Old snapshot */
    private function readSnapshotJsonOrFail(string $phpPath): array
    {
        $jsonPath = $this->snapshotPathFromPhp($phpPath);
        if (!is_file($jsonPath)) {
            throw new RuntimeException('Snapshot not found: ' . $jsonPath);
        }
        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            throw new RuntimeException('Cannot read snapshot: ' . $jsonPath);
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        // Normalize minimal shape
        $data['fields'] = $data['fields'] ?? [];
        $data['indexes'] = $data['indexes'] ?? [];
        $data['foreignkeys'] = $data['foreignkeys'] ?? [];
        return $data;
    }

    private function writeSnapshotJson(string $phpPath, array $snapshot): void
    {
        $jsonPath = $this->snapshotPathFromPhp($phpPath);
        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $ok = file_put_contents($jsonPath, (string)$json, LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException('Cannot write snapshot: ' . $jsonPath);
        }
        CLI::write('Snapshot written: ' . $jsonPath);
    }

    /**
     * Print plan including explicit rename list (if any).
     * @param array<int,array{from:string,to:string,def:array}> $renamed
     */
    private function printPlan(string $up, string $down, array $renamed): void
    {
        if (!empty($renamed)) {
            CLI::write("\nRenames detected:");
            foreach ($renamed as $r) {
                CLI::write("  - {$r['from']} -> {$r['to']}");
            }
        }
        CLI::write("\n--- PLAN: UP ---\n" . rtrim($up) . "\n--- PLAN: DOWN ---\n" . rtrim($down) . "\n");
    }
}
