<?php

namespace App\RateLimit;

final readonly class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public int $remaining,
        public int $limit,
        public int $retryAfter,
    ) {}
}
