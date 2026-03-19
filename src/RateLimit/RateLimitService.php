<?php

namespace App\RateLimit;

final class RateLimitService
{
    private const LUA_SCRIPT = <<<'LUA'
        local key = KEYS[1]
        local max_tokens = tonumber(ARGV[1])
        local window = tonumber(ARGV[2])
        local now = tonumber(ARGV[3])

        local data = redis.call('HMGET', key, 'tokens', 'last_refill')
        local tokens = tonumber(data[1])
        local last_refill = tonumber(data[2])

        if tokens == nil then
            tokens = max_tokens
            last_refill = now
        end

        local elapsed = now - last_refill
        local refill = math.floor(elapsed * max_tokens / window)
        if refill > 0 then
            tokens = math.min(max_tokens, tokens + refill)
            last_refill = now
        end

        local allowed = 0
        if tokens > 0 then
            tokens = tokens - 1
            allowed = 1
        end

        redis.call('HMSET', key, 'tokens', tokens, 'last_refill', last_refill)
        redis.call('EXPIRE', key, window * 2)

        local retry_after = 0
        if allowed == 0 then
            retry_after = math.ceil(window / max_tokens)
        end

        return {allowed, tokens, retry_after}
    LUA;

    private \Redis $redis;

    public function __construct(
        string $redisHost,
        int $redisPort,
        string $redisPassword,
        private readonly int $maxTokens = 100,
        private readonly int $windowSeconds = 60,
    ) {
        $this->redis = new \Redis();
        $this->redis->connect($redisHost, $redisPort);

        if ($redisPassword !== '') {
            $this->redis->auth($redisPassword);
        }
    }

    public function consume(string $userId): RateLimitResult
    {
        $key = sprintf('rate_limit:%s', $userId);

        /** @var array{0: int, 1: int, 2: int} $result */
        $result = $this->redis->eval(
            self::LUA_SCRIPT,
            [$key, $this->maxTokens, $this->windowSeconds, time()],
            1
        );

        return new RateLimitResult(
            allowed: (bool) $result[0],
            remaining: (int) $result[1],
            limit: $this->maxTokens,
            retryAfter: (int) $result[2],
        );
    }
}
