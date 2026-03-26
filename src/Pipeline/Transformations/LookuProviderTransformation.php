<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;
use Rakoitde\Tools\Contracts\LookupProviderInterface;

/**
 * Delegates lookup to an application-provided provider (from $context['providers']).
 */
final class LookupProviderTransformation implements TransformationInterface
{
    public function __construct(private string $providerClass, private array $options = [])
    {
    }

    public function apply($value, array $row = [], array $context = [])
    {
        $providers = $context['providers'] ?? [];
        $provider  = $providers[$this->providerClass] ?? null;

        if ($provider instanceof LookupProviderInterface) {
            // Provider can use its own DB services internally.
            return $provider->resolve($value, $row, $this->options);
        }

        // If provider is missing, return value unchanged (or throw if you prefer strictness).
        return $value;
    }
}
