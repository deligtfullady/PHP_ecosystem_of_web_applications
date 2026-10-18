<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Cache\InMemoryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryCache::class)]
class InMemoryCacheTest extends TestCase
{
    public function testGetWithMissingKeyReturnsDefault(): void
    {
        $this->assertSame('x', (new InMemoryCache())->get('nope', 'x'));
    }

    public function testSetThenGetReturnsStoredArray(): void
    {
        $cache = new InMemoryCache();

        $cache->set('k', ['a' => [1, 2]]);

        $this->assertSame(['a' => [1, 2]], $cache->get('k'));
        $this->assertTrue($cache->has('k'));
    }

    public function testSetWithNonPositiveTtlDeletesKey(): void
    {
        $cache = new InMemoryCache();
        $cache->set('k', 1);

        $cache->set('k', 2, 0);

        $this->assertFalse($cache->has('k'));
    }

    public function testDeleteAndClearRemoveKeys(): void
    {
        $cache = new InMemoryCache();
        $cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);

        $cache->delete('a');
        $this->assertFalse($cache->has('a'));

        $cache->clear();
        $this->assertSame(['b' => null, 'c' => null], $cache->getMultiple(['b', 'c']));
    }
}
