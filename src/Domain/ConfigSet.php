<?php

namespace Rakoitde\Tools\Domain;

final class ConfigSet
{
    /** @var Table[] */ public array $tables = [];
    public static function of(Table ...$t): self
    {
        $c = new self();
        $c->tables = $t;
        return $c;
    }
    public function get(string $target): ?Table
    {
        foreach ($this->tables as $t) {
            if ($t->target === $target) return $t;
        }
        return null;
    }
}
