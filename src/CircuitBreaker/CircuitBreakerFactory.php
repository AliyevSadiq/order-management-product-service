<?php

namespace App\CircuitBreaker;

use Psr\Log\LoggerInterface;

final class CircuitBreakerFactory
{
    /** @var array<string, CircuitBreaker> */
    private array $instances = [];

    private \Redis $redis;

    public function __construct(
        private readonly LoggerInterface $logger,
        string $redisHost,
        int $redisPort,
        string $redisPassword,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 30,
        private readonly int $successThreshold = 2,
    ) {
        $this->redis = new \Redis();
        $this->redis->connect($redisHost, $redisPort);

        if ($redisPassword !== '') {
            $this->redis->auth($redisPassword);
        }
    }

    public function create(string $serviceName): CircuitBreaker
    {
        if (!isset($this->instances[$serviceName])) {
            $this->instances[$serviceName] = new CircuitBreaker(
                redis: $this->redis,
                logger: $this->logger,
                serviceName: $serviceName,
                failureThreshold: $this->failureThreshold,
                recoveryTimeout: $this->recoveryTimeout,
                successThreshold: $this->successThreshold,
            );
        }

        return $this->instances[$serviceName];
    }
}
