<?php

namespace Winter\Blocks\Tests\Fixtures\Classes;

use Winter\Blocks\Classes\BlockManager;

/**
 * Test double for BlockManager that replaces the expensive buildConfigs() step
 * with a controllable, countable stand-in. This isolates the cross-request
 * cache logic (rememberConfigs()/blocksSignature()) in
 * BlockManagerTest from the CMS Halcyon layer's own object caching, which
 * would otherwise make it difficult to prove whether a given getConfigs()
 * call actually rebuilt its data or served it from Cache.
 */
class BlockManagerCacheTestDouble extends BlockManager
{
    /**
     * @var int Number of times buildConfigs() has actually been executed.
     */
    public static int $buildCallCount = 0;

    /**
     * @var array Value buildConfigs() should return the next time it is called.
     */
    public static array $buildReturn = [];

    protected function buildConfigs(string|array|null $tags = null): array
    {
        static::$buildCallCount++;

        return static::$buildReturn;
    }
}
