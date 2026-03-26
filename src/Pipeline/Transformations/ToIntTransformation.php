<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;

final class ToIntTransformation implements TransformationInterface
{
    public function apply($value, array $row = [], array $context = [])
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }
}
