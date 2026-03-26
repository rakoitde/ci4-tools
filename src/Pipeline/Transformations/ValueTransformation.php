<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;

final class ValueTransformation implements TransformationInterface
{
    public function __construct(private $constant) {}

    public function apply($value, array $row = [], array $context = [])
    {
        return $this->constant;
    }
}
