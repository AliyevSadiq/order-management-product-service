<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class ProductCacheManager
{
    private const PRODUCT_CACHE_TTL = 3600;

    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function get(string $key, callable $loader): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($loader) {
            $item->expiresAfter(self::PRODUCT_CACHE_TTL);
            return $loader();
        });
    }

    public function invalidate(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }
}
