<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;

/**
 * Concatenate parts. Placeholders beginning with ":" are sourced from $row,
 * optionally remapped via $bindings (e.g. [':first' => 'fname']).
 */
final class ConcatTransformation implements TransformationInterface
{
    /** @param string[] $parts */
    public function __construct(private array $parts, private array $bindings = [])
    {
    }

    public function apply($value, array $row = [], array $context = [])
    {
        $buf = [];
        foreach ($this->parts as $part) {
            if (is_string($part) && str_starts_with($part, ':')) {
                $key = substr($part, 1);
                $sourceKey = $this->bindings[":{$key}"] ?? $key;
                $buf[] = (string) ($row[$sourceKey] ?? '');
            } else {
                $buf[] = (string) $part;
            }
        }
        return implode('', $buf);
    }
}
