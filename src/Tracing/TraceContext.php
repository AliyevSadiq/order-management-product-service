<?php

namespace App\Tracing;

final readonly class TraceContext
{
    public function __construct(
        public string $traceId,
        public string $spanId,
        public ?string $parentSpanId = null,
        public ?string $operationName = null,
        public float $startTime = 0.0,
    ) {}

    public static function generate(?string $traceId = null, ?string $parentSpanId = null, ?string $operationName = null): self
    {
        return new self(
            traceId: $traceId ?? bin2hex(random_bytes(16)),
            spanId: bin2hex(random_bytes(8)),
            parentSpanId: $parentSpanId,
            operationName: $operationName,
            startTime: microtime(true),
        );
    }

    public function toHeaders(): array
    {
        return [
            'X-Trace-Id' => $this->traceId,
            'X-Span-Id' => $this->spanId,
            'X-Parent-Span-Id' => $this->parentSpanId ?? '',
        ];
    }
}
