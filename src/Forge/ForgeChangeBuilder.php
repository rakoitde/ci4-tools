<?php
declare(strict_types=1);

namespace Rakoitde\Tools\Forge;

/**
 * CI4 Migration Helper Classes
 *
 * - MigrationFileEditor: ersetzt ausschließlich die Bodies von up()/down().
 * - SnapshotDiffService: erzeugt ein simples, forge-freundliches Diff-Model zwischen zwei Snapshots.
 * - ForgeChangeBuilder: baut aus dem Diff PHP-Snippets mit $this->forge-Calls (create/alter/drop).
 *
 * Hinweise:
 * - Snapshots sollten die Struktur aus createJsonInfo() besitzen: table, fields, indexes, foreignkeys
 * - "fields" ist ein assoc-Array name => object/array mit min. {name,type,max_length,nullable,default,primary_key}
 * - "indexes" enthält Einträge mit {name,type,fields[]}
 * - "foreignkeys" enthält Einträge mit {constraint_name,column_name[],foreign_table_name,foreign_column_name[],on_delete,on_update}
 * - Der Builder erzeugt Strings für UP/DOWN; das Einfügen übernimmt MigrationFileEditor
 *
 * Namespace-Empfehlung:
 *   Rakoitde\\Tools\\Migrations\\MigrationFileEditor
 *   Rakoitde\\Tools\\Snapshot\\SnapshotDiffService
 *   Rakoitde\\Tools\\Forge\\ForgeChangeBuilder
 */



use Rakoitde\Tools\Support\Indenter;

/**
 * Erzeugt Forge-Statements basierend auf einem neutralen Diff-Array
 * (siehe SnapshotDiffService::diff Rückgabeformat).
 */
final class ForgeChangeBuilder
{
    public function __construct(private bool $disableFK = false, private ?Indenter $indenter = null)
    {
        $this->indenter ??= new Indenter(4);
    }

    /**
     * Erstellt kompletten CreateTable-Block (UP) inkl. Keys/FKs, sowie das passende DROP (DOWN).
     * @param string $table
     * @param array $fields assoc name=>def
     * @param array $indexes array of {name,type,fields[]}
     * @param array $foreignKeys array of FK-Arrays
     * @return array{up:string,down:string}
     */
    public function buildCreate(string $table, array $fields, array $indexes, array $foreignKeys): array
    {
        $i = $this->indenter;
        $up  = '';
        $down = '';

        if ($this->disableFK) {
            $up .= $i->withLevel(2, "\$this->db->disableForeignKeyChecks();\n\n");
        }

        // addField
        $up .= $i->withLevel(2, "\$this->forge->addField([\n");
        foreach ($fields as $f) {
            $up .= $this->fieldArrayLine($f, 3);
        }
        $up .= $i->withLevel(2, "]);\n");

        // Keys
        $up .= $this->addKeys($indexes, 2);

        // FKs
        if (count($foreignKeys) > 0) $up .= $this->addFKs($foreignKeys, 2);
        
        // createTable
        $up .= $i->withLevel(2, "\$this->forge->createTable('{$table}');\n\n");
        
        if ($this->disableFK) {
            $up .= $i->withLevel(2, "\$this->db->enableForeignKeyChecks();\n");
        }
        
        // DOWN
        $down .= $i->withLevel(2, "\$this->forge->dropTable('{$table}');\n\n");

        return [$up, $down];
    }

    /**
     * Baut ALTER-Sequenzen aus einem Diff (add/modify/drop fields, add/drop keys+FKs).
     * @param array $diff siehe SnapshotDiffService::diff
     * @return array{up:string,down:string}
     */
    public function buildAlter(array $diff): array
    {
        $i = $this->indenter; $t = (string)$diff['table'];
        $up = '';$down='';

        if ($this->disableFK) {
            $up .= $i->withLevel(2, "\$this->db->disableForeignKeyChecks();\n\n");
        }

        // MODIFY
        if (!empty($diff['modifyFields'])) {
            $up .= $i->withLevel(2, "\$this->forge->modifyColumn('{$t}', [\n");
            foreach ($diff['modifyFields'] as $f) {
                $up .= $this->fieldArrayLine($f, 3);
            }
            $up .= $i->withLevel(2, "]);\n");
            // Down: leider unbekannter vorheriger Zustand → hier keine automatische Rücknahme
        }

        // ADD FIELDS
        foreach ($diff['addFields'] as $f) {
            $up   .= $i->withLevel(2, "\$this->forge->addColumn('{$t}', [\n");
            $up   .= $this->fieldArrayLine($f, 3);
            $up   .= $i->withLevel(2, "]);\n");
            $down .= $i->withLevel(2, "\$this->forge->dropColumn('{$t}', '{$f['name']}');\n");
        }

        // DROP FIELDS
        foreach ($diff['dropFields'] as $name) {
            $up   .= $i->withLevel(2, "\$this->forge->dropColumn('{$t}', '{$name}');\n");
            // Down: ohne Vollsnapshot des alten Feldes nicht rekonstruierbar
        }

        // KEYS
        if (!empty($diff['addIndexes'])) {
            $up   .= $this->addKeys($diff['addIndexes'], 2);
        }
        if (!empty($diff['dropIndexes'])) {
            foreach ($diff['dropIndexes'] as $idx) {
                $name = $idx['name'];
                $up   .= $i->withLevel(2, "\$this->forge->dropKey('{$t}', '{$name}', false);\n");
                // Down-Recreation ohne Originaltyp möglich, aber hier weggelassen
            }
        }

        // FKs
        if (!empty($diff['addFKs'])) {
            $up   .= $this->addFKs($diff['addFKs'], 2, $t);
        }
        if (!empty($diff['dropFKs'])) {
            foreach ($diff['dropFKs'] as $fk) {
                $name = $fk['constraint_name'];
                $up   .= $i->withLevel(2, "\$this->forge->dropForeignKey('{$t}', '{$name}');\n");
            }
        }

        if ($this->disableFK) {
            $up .= $i->withLevel(2, "\$this->db->enableForeignKeyChecks();\n");
        }

        return [$up, $down];
    }

    private function fieldArrayLine(array $f, int $level): string
    {
        $i = $this->indenter; $l = '';
        $l .= $i->withLevel($level, "'{$f['name']}' => [\n");
        $l .= $i->withLevel($level+1, "'type' => '{$f['type']}',\n");
        if (!empty($f['max_length'])) {
            $l .= $i->withLevel($level+1, "'constraint' => {$f['max_length']},\n");
        }
        if (!empty($f['nullable'])) {
            $l .= $i->withLevel($level+1, "'null' => true,\n");
        }
        if (array_key_exists('default', $f) && $f['default'] !== null && $f['default'] !== '') {
            $def = is_numeric($f['default']) ? (string)$f['default'] : ("'" . str_replace("'", "\\'", (string)$f['default']) . "'");
            $l .= $i->withLevel($level+1, "'default' => {$def},\n");
        }
        if (!empty($f['primary_key'])) {
            $l .= $i->withLevel($level+1, "'auto_increment' => true,\n");
        }
        $l .= $i->withLevel($level, "],\n");
        return $l;
    }

    private function addKeys(array $indexes, int $level): string
    {
        $i = $this->indenter; $up = '';
        foreach ($indexes as $idx) {
            $fields = array_map(static fn($s) => "'" . $s . "'", (array)$idx['fields']);
            $fieldArray = '[' . implode(', ', $fields) . ']';
            $type = strtoupper((string)$idx['type']);
            $name = (string)$idx['name'];
            if ($type === 'PRIMARY') {
                $up .= $i->withLevel($level, "\$this->forge->addKey({$fieldArray}, true);\n");
            } elseif ($type === 'UNIQUE') {
                $up .= $i->withLevel($level, "\$this->forge->addKey({$fieldArray}, false, true, '{$name}');\n");
            } else { // INDEX/KEY
                $up .= $i->withLevel($level, "\$this->forge->addKey({$fieldArray}, false, false, '{$name}');\n");
            }
        }
        return $up;
    }

    /**
     * @param array $fks list of FKs (assoc)
     * @param int $level
     * @param string|null $table only für ALTER-Kontext nötig
     */
    private function addFKs(array $fks, int $level, ?string $table = null): string
    {
        $i = $this->indenter; $up = '';
        foreach ($fks as $fk) {
            $cols = $fk['column_name'];
            $refCols = $fk['foreign_column_name'];
            $refTable = $fk['foreign_table_name'];
            $onDelete = $fk['on_delete'] ?: '';
            $onUpdate = $fk['on_update'] ?: '';
            $name     = $fk['constraint_name'];

            $lcols = count($cols) > 1 ? '['.implode(', ', array_map(fn($c)=>"'{$c}'", $cols)).']' : "'{$cols[0]}'";
            $lref  = count($refCols) > 1 ? '['.implode(', ', array_map(fn($c)=>"'{$c}'", $refCols)).']' : "'{$refCols[0]}'";

            // Im CREATE-Kontext wird addForeignKey vor createTable() aufgerufen (ohne Table-Name).
            // Im ALTER-Kontext ebenfalls addForeignKey() auf dem Forge – CI4 akzeptiert auch hier den gleichen Call.
            $up .= $i->withLevel($level, "\$this->forge->addForeignKey({$lcols}, '{$refTable}', {$lref}, '{$onDelete}', '{$onUpdate}', '{$name}');\n");
        }
        return $up;
    }
}
