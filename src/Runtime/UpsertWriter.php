<?php

namespace Rakoitde\Tools\Runtime;

use Rakoitde\Tools\Domain\Table;
use Rakoitde\Tools\Pipeline\SkipValue;

final class UpsertWriter
{
    /** @var array<string,array<string,bool>> */
    private array $fieldSetCache = [];

    public function __construct(private \CodeIgniter\Database\BaseConnection $db) {}

    public function upsert(Table $t, array $rows): int
    {
        if (!$rows) return 0;

        $table = $t->target;

        $filtered = [];
        foreach ($rows as $row) {
            $row = $this->filterSkippedAndUnknown($table, $row);
            if (!empty($row)) {
                $filtered[] = $row;
            }
        }
        if (empty($filtered)) {
            return 0;
        }

        $colSet = [];
        foreach ($filtered as $r) {
            foreach (array_keys($r) as $k) {
                $colSet[$k] = true;
            }
        }
        $cols = array_keys($colSet);
        sort($cols);

        $aff = 0;
        foreach (array_chunk($filtered, 500) as $chunk) {
            $sql = $this->build($table, $cols, $chunk);
            $this->db->query($sql);
            $aff += $this->db->affectedRows();
        }
        return $aff;
    }

    private function build(string $table, array $cols, array $rows): string
    {
        $esc = function ($v) {
            if ($v === null) return 'NULL';
            // nutze DB-escape; wenn das in deinem Projekt verfügbar ist:
            return $this->db->escape($v);
        };

        $colsSql = implode(',', array_map(fn($c) => "`$c`", $cols));

        $vals = [];
        foreach ($rows as $r) {
            $v = [];
            foreach ($cols as $c) {
                $v[] = $esc($r[$c] ?? null);
            }
            $vals[] = '(' . implode(',', $v) . ')';
        }

        $upd = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));

        return "INSERT INTO `$table` ($colsSql) VALUES " . implode(',', $vals) . " ON DUPLICATE KEY UPDATE $upd";
    }

    /** Remove SkipValue columns and optionally drop unknown columns */
    private function filterSkippedAndUnknown(string $table, array $row): array
    {
        // 1) Skip explicit markers
        foreach ($row as $k => $v) {
            if ($v instanceof \Rakoitde\Tools\Pipeline\SkipValue) {
                unset($row[$k]);
            }
        }

        // 2) Guard: remove keys that are not real columns (prevents "Unknown column" errors)
        $valid = $this->getFieldSet($table);
        foreach (array_keys($row) as $k) {
            if (!isset($valid[$k])) {
                unset($row[$k]);
            }
        }
        return $row;
    }

    /** @return array<string,bool> */
    private function getFieldSet(string $table): array
    {
        if (!isset($this->fieldSetCache[$table])) {
            $names = $this->db->getFieldNames($table) ?? [];
            $this->fieldSetCache[$table] = array_fill_keys($names, true);
        }
        return $this->fieldSetCache[$table];
    }
}
