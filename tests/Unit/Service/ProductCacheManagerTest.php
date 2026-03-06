<?php

namespace App\Tests\Unit\Service;

use App\Service\ProductCacheManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ProductCacheManagerTest extends TestCase
{
    private CacheInterface&MockObject $cache;
    private ProductCacheManager $cacheManager;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cacheManager = new ProductCacheManager($this->cache);
    }

    public function testGetDelegatesToCacheWithTtl(): void
    {
        $expectedResult = ['id' => '123', 'name' => 'Test Product'];

        $this->cache->expects(self::once())
            ->method('get')
            ->with('product_123', self::isType('callable'))
            ->willReturnCallback(function (string $key, callable $callback) use ($expectedResult) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects(self::once())
                    ->method('expiresAfter')
                    ->with(3600);
                return $callback($item);
            });

        $loader = fn() => $expectedResult;
        $result = $this->cacheManager->get('product_123', $loader);

        self::assertSame($expectedResult, $result);
    }

    public function testInvalidateDeletesSingleKey(): void
    {
        $this->cache->expects(self::once())
            ->method('delete')
            ->with('product_123');

        $this->cacheManager->invalidate('product_123');
    }

    public function testInvalidateDeletesMultipleKeys(): void
    {
        $this->cache->expects(self::exactly(2))
            ->method('delete')
            ->willReturnCallback(function (string $key): bool {
                self::assertContains($key, ['product_123', 'products_list']);
                return true;
            });

        $this->cacheManager->invalidate('product_123', 'products_list');
    }
}
