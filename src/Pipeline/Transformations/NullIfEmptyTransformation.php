<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;

final class NullIfEmptyTransformation implements TransformationInterface
{
    public function apply($value, array $row = [], array $context = [])
    {
        return $value === '' ? null : $value;
    }
}
