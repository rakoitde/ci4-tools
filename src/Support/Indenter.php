<?php
declare(strict_types=1);

namespace Rakoitde\Tools\Support;

/**
 * CI4 Migration Helper Class
 *
 * - MigrationFileEditor: ersetzt ausschließlich die Bodies von up()/down().
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

 
final class Indenter
{
    public function __construct(private int $spacesPerLevel = 4) {}

    public function withLevel(int $level, string $line): string
    {
        return str_repeat(' ', max(0, $level) * $this->spacesPerLevel) . $line;
    }
}
