<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakode\Tools\Pipeline\TransformationInterface;

final class LowerTransformation implements TransformationInterface
{
    public function apply($value, array $row = [], array $context = [])
    {
        return is_string($value) ? strtolower($value) : $value;
    }
}
