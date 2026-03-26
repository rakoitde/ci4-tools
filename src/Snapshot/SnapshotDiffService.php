<?php
declare(strict_types=1);

namespace Rakoitde\Tools\Snapshot;

/**
 * CI4 Migration Helper Classes
 *
 * - SnapshotDiffService: erzeugt ein simples, forge-freundliches Diff-Model zwischen zwei Snapshots.
 *
 * Hinweise:
 * - Snapshots sollten die Struktur aus createJsonInfo() besitzen: table, fields, indexes, foreignkeys
 * - "fields" ist ein assoc-Array name => object/array mit min. {name,type,max_length,nullable,default,primary_key}
 * - "indexes" enthält Einträge mit {name,type,fields[]}
 * - "foreignkeys" enthält Einträge mit {constraint_name,column_name[],foreign_table_name,foreign_column_name[],on_delete,on_update}
 *
 */

use RuntimeException;

/**
 * Minimaler Diff-Service zwischen zwei Snapshots (arrays)
 * Erzeugt ein neutrales Diff-Modell für den Forge-Builder.
 */
final class SnapshotDiffService
{
    /**
     * @param array $old Old snapshot (assoc)
     * @param array $new New snapshot (assoc)
     * @return array{table:string, addFields:array, modifyFields:array, dropFields:array, addIndexes:array, dropIndexes:array, addFKs:array, dropFKs:array}
     */
    public function diff(array $old, array $new): array
    {

        $table = (string)($new['table'] ?? $old['table'] ?? '');
        if ($table === '') {
            throw new RuntimeException('Table name missing in snapshots.');
        }

        $oldFields = $this->normalizeFields($old['fields'] ?? []);
        $newFields = $this->normalizeFields($new['fields'] ?? []);

        $addFields = [];
        $modifyFields = [];
        $dropFields = [];

        foreach ($newFields as $name => $nf) {
            if (!array_key_exists($name, $oldFields)) {
                $addFields[$name] = $nf;
                continue;
            }
            if ($this->fieldChanged($oldFields[$name], $nf)) {
                $modifyFields[$name] = $nf;
            }
        }
        foreach ($oldFields as $name => $_) {
            if (!array_key_exists($name, $newFields)) {
                $dropFields[] = $name;
            }
        }

        // Indexes
        $oldIdx = $this->normalizeIndexes($old['indexes'] ?? []);
        $newIdx = $this->normalizeIndexes($new['indexes'] ?? []);
        [$addIndexes, $dropIndexes] = $this->diffIndexes($oldIdx, $newIdx);

        // FKs
        $oldFK = $this->normalizeFKs($old['foreignkeys'] ?? []);
        $newFK = $this->normalizeFKs($new['foreignkeys'] ?? []);
        [$addFKs, $dropFKs] = $this->diffFKs($oldFK, $newFK);

        return compact('table','addFields','modifyFields','dropFields','addIndexes','dropIndexes','addFKs','dropFKs');
    }

    private function normalizeFields(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            // Support sowohl object als auch array
            $arr = is_object($v) ? get_object_vars($v) : (array)$v;
            $name = (string)($arr['name'] ?? $k);
            $out[$name] = [
                'name'        => $name,
                'type'        => (string)($arr['type'] ?? 'VARCHAR'),
                'max_length'  => $arr['max_length'] ?? null,
                'nullable'    => (bool)($arr['nullable'] ?? false),
                'default'     => $arr['default'] ?? null,
                'primary_key' => (int)($arr['primary_key'] ?? 0),
            ];
        }
        return $out;
    }

    private function fieldChanged(array $a, array $b): bool
    {
        $keys = ['type','max_length','nullable','default','primary_key'];
        foreach ($keys as $k) {
            if (($a[$k] ?? null) !== ($b[$k] ?? null)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeIndexes(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $arr = is_object($v) ? get_object_vars($v) : (array)$v;
            $name   = (string)($arr['name'] ?? $k);
            $type   = strtoupper((string)($arr['type'] ?? 'INDEX'));
            $fields = array_values($arr['fields'] ?? []);
            sort($fields);
            $out[$name] = ['name'=>$name,'type'=>$type,'fields'=>$fields];
        }
        return $out;
    }

    /** @return array{0:array,1:array} [add, drop] */
    private function diffIndexes(array $old, array $new): array
    {
        $add = [];$drop = [];
        foreach ($new as $name => $n) {
            if (!isset($old[$name]) || $old[$name] !== $n) {
                $add[$name] = $n;
            }
        }
        foreach ($old as $name => $o) {
            if (!isset($new[$name])) {
                $drop[$name] = $o;
            }
        }
        return [$add,$drop];
    }

    private function normalizeFKs(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $arr = is_object($v) ? get_object_vars($v) : (array)$v;
            $name = (string)($arr['constraint_name'] ?? $k);
            $out[$name] = [
                'constraint_name'    => $name,
                'column_name'        => array_values($arr['column_name'] ?? []),
                'foreign_table_name' => (string)($arr['foreign_table_name'] ?? ''),
                'foreign_column_name'=> array_values($arr['foreign_column_name'] ?? []),
                'on_delete'          => (string)($arr['on_delete'] ?? ''),
                'on_update'          => (string)($arr['on_update'] ?? ''),
            ];
        }
        // Sortiere Spalten für deterministischen Vergleich
        foreach ($out as &$fk) {
            sort($fk['column_name']);
            sort($fk['foreign_column_name']);
        }
        return $out;
    }

    /** @return array{0:array,1:array} [add, drop] */
    private function diffFKs(array $old, array $new): array
    {
        $add = [];$drop = [];
        foreach ($new as $name => $n) {
            if (!isset($old[$name]) || $old[$name] !== $n) {
                $add[$name] = $n;
            }
        }
        foreach ($old as $name => $o) {
            if (!isset($new[$name])) {
                $drop[$name] = $o;
            }
        }
        return [$add,$drop];
    }
}