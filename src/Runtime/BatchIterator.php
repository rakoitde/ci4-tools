<?php

namespace Rakoitde\Tools\Runtime;

final class BatchIterator implements \IteratorAggregate
{
    public function __construct(private \Closure $factory, private int $size = 1000) {}
    public function getIterator(): \Traversable
    {
        $off = 0;
        while (true) {
            $b = ($this->factory)();
            $rows = $b->limit($this->size, $off)->get()->getResultArray();
            if (!$rows) break;
            $off += $this->size;
            yield $rows;
        }
    }
}
