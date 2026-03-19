<?php

namespace App\Tracing;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TracingService
{
    private ?TraceContext $currentContext = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $jaegerEndpoint,
        private readonly string $serviceName,
    ) {}

    public function startSpan(string $operationName, ?string $traceId = null, ?string $parentSpanId = null): TraceContext
    {
        $context = TraceContext::generate(
            traceId: $traceId,
            parentSpanId: $parentSpanId,
            operationName: $operationName,
        );

        if ($this->currentContext === null) {
            $this->currentContext = $context;
        }

        return $context;
    }

    public function finishSpan(TraceContext $context, int $statusCode = 200): void
    {
        $duration = (int) ((microtime(true) - $context->startTime) * 1_000_000);

        $span = [
            'traceId' => $context->traceId,
            'id' => $context->spanId,
            'name' => $context->operationName ?? 'unknown',
            'timestamp' => (int) ($context->startTime * 1_000_000),
            'duration' => $duration,
            'localEndpoint' => [
                'serviceName' => $this->serviceName,
            ],
            'tags' => [
                'http.status_code' => (string) $statusCode,
            ],
        ];

        if ($context->parentSpanId !== null) {
            $span['parentId'] = $context->parentSpanId;
        }

        try {
            $this->httpClient->request('POST', $this->jaegerEndpoint, [
                'json' => [$span],
                'timeout' => 2,
            ]);
        } catch (\Throwable $e) {
            $this->logger->debug('Failed to send span to Jaeger', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getCurrentContext(): ?TraceContext
    {
        return $this->currentContext;
    }

    public function setCurrentContext(?TraceContext $context): void
    {
        $this->currentContext = $context;
    }
}
