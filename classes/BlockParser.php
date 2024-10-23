<?php

namespace Winter\Blocks\Classes;

use Winter\Storm\Halcyon\Processors\SectionParser;
use Yaml;

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
        $parsed = Yaml::parse($settings);
        // Ensure that the parsed settings returns an array (errors return input string)
        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Renders the provided settings data into a string that can be stored in the Settings section
     */
    public static function renderSettings(array $data): string
    {
        return is_string($data['yaml']) ? $data['yaml'] : Yaml::render($data);
    }

    /**
     * Parses Halcyon section content.
     * The expected file format is following:
     *
     *     INI settings section
     *     ==
     *     PHP code section
     *     ==
     *     Twig markup section
     *
     * If the content has only 2 sections they are parsed as settings and markup.
     * If there is only a single section, it is parsed as markup.
     *
     * Returns an array with the following elements: (array|null) 'settings',
     * (string|null) 'markup', (string|null) 'code'.
     */
    public static function parse(string $content, array $options = []): array
    {
        $sectionOptions = array_merge([
            'isCompoundObject' => true
        ], $options);
        extract($sectionOptions);

        $result = [
            'settings' => [],
            'code'     => null,
            'markup'   => null,
            'yaml'     => null
        ];

        if (!isset($isCompoundObject) || $isCompoundObject === false || !strlen($content)) {
            return $result;
        }

        $sections = static::parseIntoSections($content);
        $count = count($sections);
        foreach ($sections as &$section) {
            $section = trim($section);
        }

        if ($count >= 3) {
            $result['yaml'] = $sections[0];
            $result['settings'] = static::parseSettings($sections[0]);
            $result['code'] = static::parseCode($sections[1]);
            $result['markup'] = static::parseMarkup($sections[2]);
        } elseif ($count == 2) {
            $result['yaml'] = $sections[0];
            $result['settings'] = static::parseSettings($sections[0]);
            $result['markup'] = static::parseMarkup($sections[1]);
        } elseif ($count == 1) {
            $result['markup'] = static::parseMarkup($sections[0]);
        }

        return $result;
    }
}
