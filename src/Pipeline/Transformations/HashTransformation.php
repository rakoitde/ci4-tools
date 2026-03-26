<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;

final class HashTransformation implements TransformationInterface
{
    public function __construct(private string $algo = 'sha256') {}

    public function apply($value, array $row = [], array $context = [])
    {
        return hash($this->algo, (string) $value);
    }
}
