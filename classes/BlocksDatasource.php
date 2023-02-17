<?php

namespace Winter\Blocks\Classes;

use Winter\Storm\Exception\SystemException;
use Winter\Storm\Halcyon\Datasource\Datasource;

class BlocksDatasource extends Datasource
{
    /**
     * @var array [key => path] List of blocks managed by the BlockManager
     */
    protected array $blocks;

    public function __construct()
    {
        $this->processor = new BlockProcessor();
        $this->blocks = BlockManager::instance()->getRegisteredBlocks();
    }

    /**
     * @inheritDoc
     */
    public function selectOne(string $dirName, string $fileName, string $extension): ?array
    {
        if ($dirName !== 'blocks' || $extension !== 'block' || !isset($this->blocks[$fileName])) {
            return null;
        }

        return [
            'fileName' => $fileName . '.' . $extension,
            'content'  => file_get_contents($this->blocks[$fileName]),
            'mtime'    => filemtime($this->blocks[$fileName]),
        ];
    }

    /**
     * @inheritDoc
     */
    public function select(string $dirName, array $options = []): array
    {
        // Prepare query options
        $queryOptions = array_merge([
            'columns'     => null,  // Only return specific columns (fileName, mtime, content)
            'extensions'  => null,  // Match specified extensions
            'fileMatch'   => null,  // Match the file name using fnmatch()
            'orders'      => null,  // @todo
            'limit'       => null,  // @todo
            'offset'      => null   // @todo
        ], $options);
        extract($queryOptions);

        if (isset($columns)) {
            if ($columns === ['*'] || !is_array($columns)) {
                $columns = null;
            } else {
                $columns = array_flip($columns);
            }
        }

        if ($dirName !== 'blocks' || (isset($extensions) && !in_array('block', $extensions))) {
            return [];
        }

        $result = [];
        foreach ($this->blocks as $fileName => $path) {
            $item = [
                'fileName' => $fileName . '.block',
            ];

            if (!isset($columns) || array_key_exists('content', $columns)) {
                $item['content'] = file_get_contents($path);
            }

            if (!isset($columns) || array_key_exists('mtime', $columns)) {
                $item['mtime'] = filemtime($path);
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function insert(string $dirName, string $fileName, string $extension, string $content): int
    {
        throw new SystemException('insert() is not implemented on the BlocksDatasource');
    }

    /**
     * @inheritDoc
     */
    public function update(string $dirName, string $fileName, string $extension, string $content, ?string $oldFileName = null, ?string $oldExtension = null): int
    {
        throw new SystemException('update() is not implemented on the BlocksDatasource');
    }

    /**
     * @inheritDoc
     */
    public function delete(string $dirName, string $fileName, string $extension): bool
    {
        throw new SystemException('delete() is not implemented on the BlocksDatasource');
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $dirName, string $fileName, string $extension): ?int
    {
        return $this->selectOne($dirName, $fileName, $extension)['mtime'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function makeCacheKey(string $name = ''): string
    {
        return hash('crc32b', $name);
    }

    /**
     * @inheritDoc
     */
    public function getPathsCacheKey(): string
    {
        return 'halcyon-datastore-blocks-' . md5(json_encode($this->getAvailablePaths()));
    }

    /**
     * @inheritDoc
     */
    public function getAvailablePaths(): array
    {
        $paths = [];
        foreach ($this->blocks as $block => $path) {
            $paths["blocks/$block.block"] = true;
        }
        return $paths;
    }
}
