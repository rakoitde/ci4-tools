<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;

final class LookupInlineTransformation implements TransformationInterface
{
    public function __construct(private array $map, private $default = null) {}

    public function apply($value, array $row = [], array $context = [])
    {
        return array_key_exists($value, $this->map) ? $this->map[$value] : ($default ?? $value);
    }
}
