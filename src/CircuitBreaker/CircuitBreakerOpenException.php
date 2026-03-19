<?php

namespace App\CircuitBreaker;

final class CircuitBreakerOpenException extends \RuntimeException
{
    public function __construct(string $serviceName)
    {
        parent::__construct(sprintf('Circuit breaker is open for service "%s". Request rejected.', $serviceName));
    }
}
