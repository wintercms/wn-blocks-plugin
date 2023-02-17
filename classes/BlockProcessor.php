<?php

namespace Winter\Blocks\Classes;

use Winter\Storm\Halcyon\Builder;
use Winter\Storm\Halcyon\Processors\Processor;

/**
 * The BlockProcessor class.
 */
class BlockProcessor extends Processor
{
    /**
     * Helper to break down template content in to a useful array.
     * @param  \Winter\Storm\Halcyon\Builder  $query
     * @param  array|null  $result
     * @param  string  $fileName
     * @return array
     */
    protected function parseTemplateContent($query, $result, $fileName)
    {
        $options = [
            'isCompoundObject' => $query->getModel()->isCompoundObject()
        ];

        $content = array_get($result, 'content', '');

        $processed = BlockParser::parse($content, $options);

        return [
            'fileName' => $fileName,
            'content' => $content,
            'mtime' => array_get($result, 'mtime'),
            'markup' => $processed['markup'],
            'code' => $processed['code']
        ] + $processed['settings'];
    }

    /**
     * Process the data in to an insert action.
     *
     * @param  \Winter\Storm\Halcyon\Builder  $query
     * @param  array  $data
     * @return string
     */
    public function processInsert(Builder $query, $data)
    {
        $options = [
            'wrapCodeInPhpTags' => $query->getModel()->getWrapCode(),
            'isCompoundObject' => $query->getModel()->isCompoundObject()
        ];

        return BlockParser::render($data, $options);
    }

    /**
     * Process the data in to an update action.
     *
     * @param  \Winter\Storm\Halcyon\Builder  $query
     * @param  array  $data
     * @return string
     */
    public function processUpdate(Builder $query, $data)
    {
        $options = [
            'wrapCodeInPhpTags' => $query->getModel()->getWrapCode(),
            'isCompoundObject' => $query->getModel()->isCompoundObject()
        ];

        $existingData = $query->getModel()->attributesToArray();

        return BlockParser::render($data + $existingData, $options);
    }
}
