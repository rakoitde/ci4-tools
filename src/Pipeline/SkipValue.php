<?php

namespace Rakoitde\Tools\Pipeline;

/**
 * Sentinel used by transformations to signal:
 * "Do NOT write this column to the target table."
 */
final class SkipValue
{
    private static ?self $instance = null;
    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}
