<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;

final class UpperTransformation implements TransformationInterface
{
    public function apply($value, array $row = [], array $context = [])
    {
        return is_string($value) ? strtoupper($value) : $value;
    }
}
