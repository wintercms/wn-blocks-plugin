<?php

namespace Winter\Blocks\Classes;

use Yaml;
use Winter\Storm\Halcyon\Processors\SectionParser;

/**
 * Parses a block file into its component parts.
 */
class BlockParser extends SectionParser
{
    /**
     * Parses the Settings section into an array
     */
    public static function parseSettings(string $settings): array
    {
        return Yaml::parse($settings);
    }

    /**
     * Renders the provided settings data into a string that can be stored in the Settings section
     */
    public static function renderSettings(array $data): string
    {
        return Yaml::render($data);
    }
}
