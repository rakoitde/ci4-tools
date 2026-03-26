<?php

namespace Rakoitde\Tools\Domain;

final class FKGraph
{
    public static function topo(array $tables): array
    {
        $nodes = [];
        $edges = [];
        foreach ($tables as $t) {
            $nodes[$t->target] = true;
        }
        foreach ($tables as $t) {
            foreach ($t->columns as $c) {
                if ($c->foreign ?? null) {
                    $p = $c->foreign['table'];
                    $ch = $t->target;
                    $edges[$p] = $edges[$p] ?? [];
                    if (!in_array($ch, $edges[$p], true)) $edges[$p][] = $ch;
                }
            }
        }
        $in = array_fill_keys(array_keys($nodes), 0);
        foreach ($edges as $p => $chs) foreach ($chs as $ch) $in[$ch]++;
        $q = [];
        foreach ($in as $n => $d) if ($d === 0) $q[] = $n;
        $order = [];
        while ($q) {
            $n = array_shift($q);
            $order[] = $n;
            foreach (($edges[$n] ?? []) as $ch) {
                $in[$ch]--;
                if ($in[$ch] === 0) $q[] = $ch;
            }
        }
        return count($order) === count($nodes) ? $order : array_keys($nodes);
    }
}
