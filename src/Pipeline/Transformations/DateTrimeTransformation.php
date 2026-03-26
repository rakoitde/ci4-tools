<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;

final class DateTimeTransformation implements TransformationInterface
{
    public function __construct(private ?string $fromFormat = null) {}

    public function apply($value, array $row = [], array $context = [])
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value === null || $value === '') {
            return null;
        }
        $dt = $this->fromFormat
            ? \DateTime::createFromFormat($this->fromFormat, (string) $value)
            : new \DateTime((string) $value);

        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }
}
