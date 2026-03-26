<?php

namespace Rakoitde\Tools\Domain;

use Rakoitde\Tools\Contracts\LookupProviderInterface;
use Rakoitde\Tools\Pipeline\TransformationInterface;
use Rakoitde\Tools\Pipeline\TransformationRegistry;
use Rakoitde\Tools\Pipeline\Transformations\LookupInlineTransformation;
use Rakoitde\Tools\Pipeline\Transformations\LookupProviderTransformation;

/**
 * Compiles column specs into executable transformation chains.
 * Execution is now delegated to individual Transformation classes.
 */
final class Pipeline
{
    /** @var array<string, array{source:string, chain:TransformationInterface[]}> */
    private array $compiled;

    private function __construct(array $compiled)
    {
        $this->compiled = $compiled;
    }

    public static function compile(Table $table, ?TransformationRegistry $registry = null): self
    {
        $registry ??= TransformationRegistry::withDefaultMappings();

        $compiled = [];
        foreach ($table->columns as $columnSpec) {
            $chain = [];

            // 1) Compile declared step ops on the column into concrete transformations
            foreach (($columnSpec->steps ?? []) as $step) {
                $op = $step['op'] ?? null;
                if (!$op) {
                    continue;
                }
                $chain[] = $registry->create($op, $step, $columnSpec);
            }

            // 2) Inline lookup map (compatible with existing DSL properties)
            if ($columnSpec->inlineMap !== null) {
                $chain[] = new LookupInlineTransformation(
                    $columnSpec->inlineMap,
                    $columnSpec->inlineDefault
                );
            }

            // 3) Provider lookup (compatible with existing DSL properties)
            if (!empty($columnSpec->providerClass)) {
                $chain[] = new LookupProviderTransformation(
                    $columnSpec->providerClass,
                    $columnSpec->providerOptions ?? []
                );
            }

            $compiled[$columnSpec->target] = [
                'source' => $columnSpec->source,
                'chain'  => $chain,
            ];
        }

        return new self($compiled);
    }

    /**
     * Apply all compiled transformations to a legacy row.
     *
     * Context SHOULD provide:
     *  - 'providers' => [class-string => LookupProviderInterface]
     *  - 'db'        => \CodeIgniter\Database\BaseConnection (for DB-based lookups)
     */
    public function apply(array $row, array $context = []): array
    {
        $out = [];

        foreach ($this->compiled as $target => $spec) {
            $value = $row[$spec['source']] ?? null;

            /** @var TransformationInterface $transformation */
            foreach ($spec['chain'] as $transformation) {
                $value = $transformation->apply($value, $row, $context);
            }

            $out[$target] = $value;
        }
        return $out;
    }
}
