<?php

namespace App\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class TraceContextProcessor implements ProcessorInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return $record;
        }

        $context = $request->attributes->get('trace_context');

        if ($context === null) {
            return $record;
        }

        $extra = $record->extra;
        $extra['trace_id'] = $context->traceId;
        $extra['span_id'] = $context->spanId;

        return $record->with(extra: $extra);
    }
}
