<?php

namespace Rakoitde\Tools\Pipeline;

interface TransformationInterface
{
    /**
     * Apply transformation to a single field value.
     *
     * @param mixed $value   current value
     * @param array $row     full legacy row for context
     * @param array $context shared pipeline context (e.g. ['providers' => ..., 'db' => ...])
     * @return mixed
     */
    public function apply($value, array $row = [], array $context = []);
}
