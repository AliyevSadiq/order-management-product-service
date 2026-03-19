<?php

namespace App\EventListener;

use App\Tracing\TracingService;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class TracingListener
{
    public function __construct(
        private TracingService $tracingService,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $traceId = $request->headers->get('X-Trace-Id');
        $parentSpanId = $request->headers->get('X-Span-Id');

        $operationName = sprintf('%s %s', $request->getMethod(), $request->getPathInfo());

        $context = $this->tracingService->startSpan($operationName, $traceId, $parentSpanId);

        $request->attributes->set('trace_context', $context);
    }
}
