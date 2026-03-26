<?php

namespace Rakoitde\Tools\Support;

use Rakoitde\Tools\Domain\ConfigSet;
use Rakoitde\Tools\Domain\Table;

final class ConfigLoader
{
    public static function fromArray(array $t): ConfigSet
    {
        $c = new ConfigSet();
        foreach ($t as $x) {
            if ($x instanceof Table) $c->tables[] = $x;
        }
        return $c;
    }
}
