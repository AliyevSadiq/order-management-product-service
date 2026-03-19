<?php

namespace App\CircuitBreaker;

use Psr\Log\LoggerInterface;

final class CircuitBreaker
{
    private const KEY_STATE = 'circuit_breaker:%s:state';
    private const KEY_FAILURES = 'circuit_breaker:%s:failures';
    private const KEY_LAST_FAILURE = 'circuit_breaker:%s:last_failure';
    private const KEY_SUCCESSES = 'circuit_breaker:%s:successes';

    public function __construct(
        private readonly \Redis $redis,
        private readonly LoggerInterface $logger,
        private readonly string $serviceName,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 30,
        private readonly int $successThreshold = 2,
    ) {}

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws CircuitBreakerOpenException
     */
    public function call(callable $operation): mixed
    {
        $state = $this->getState();

        if ($state === CircuitBreakerState::Open) {
            if ($this->isRecoveryTimeoutExpired()) {
                $this->transitionTo(CircuitBreakerState::HalfOpen);
            } else {
                $this->logger->warning('Circuit breaker is OPEN, rejecting request', [
                    'service' => $this->serviceName,
                ]);
                throw new CircuitBreakerOpenException($this->serviceName);
            }
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function getState(): CircuitBreakerState
    {
        $state = $this->redis->get($this->key(self::KEY_STATE));

        if ($state === false) {
            return CircuitBreakerState::Closed;
        }

        return CircuitBreakerState::tryFrom($state) ?? CircuitBreakerState::Closed;
    }

    private function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === CircuitBreakerState::HalfOpen) {
            $successes = (int) $this->redis->incr($this->key(self::KEY_SUCCESSES));

            if ($successes >= $this->successThreshold) {
                $this->transitionTo(CircuitBreakerState::Closed);
                $this->logger->info('Circuit breaker CLOSED after successful recovery', [
                    'service' => $this->serviceName,
                ]);
            }
        } elseif ($state === CircuitBreakerState::Closed) {
            $this->redis->set($this->key(self::KEY_FAILURES), 0);
        }
    }

    private function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === CircuitBreakerState::HalfOpen) {
            $this->transitionTo(CircuitBreakerState::Open);
            $this->logger->warning('Circuit breaker OPENED from half-open state', [
                'service' => $this->serviceName,
            ]);
            return;
        }

        $failures = (int) $this->redis->incr($this->key(self::KEY_FAILURES));
        $this->redis->set($this->key(self::KEY_LAST_FAILURE), time());

        if ($failures >= $this->failureThreshold) {
            $this->transitionTo(CircuitBreakerState::Open);
            $this->logger->warning('Circuit breaker OPENED after reaching failure threshold', [
                'service' => $this->serviceName,
                'failures' => $failures,
                'threshold' => $this->failureThreshold,
            ]);
        }
    }

    private function transitionTo(CircuitBreakerState $newState): void
    {
        $this->redis->set($this->key(self::KEY_STATE), $newState->value);

        if ($newState === CircuitBreakerState::Closed) {
            $this->redis->set($this->key(self::KEY_FAILURES), 0);
            $this->redis->set($this->key(self::KEY_SUCCESSES), 0);
        } elseif ($newState === CircuitBreakerState::Open) {
            $this->redis->set($this->key(self::KEY_LAST_FAILURE), time());
            $this->redis->set($this->key(self::KEY_SUCCESSES), 0);
        } elseif ($newState === CircuitBreakerState::HalfOpen) {
            $this->redis->set($this->key(self::KEY_SUCCESSES), 0);
        }
    }

    private function isRecoveryTimeoutExpired(): bool
    {
        $lastFailure = (int) $this->redis->get($this->key(self::KEY_LAST_FAILURE));

        return (time() - $lastFailure) >= $this->recoveryTimeout;
    }

    private function key(string $pattern): string
    {
        return sprintf($pattern, $this->serviceName);
    }
}
