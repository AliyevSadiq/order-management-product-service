<?php

namespace App\EventListener;

use App\Tracing\TracingService;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final readonly class TracingFinishListener
{
    public function __construct(
        private TracingService $tracingService,
    ) {}

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $context = $request->attributes->get('trace_context');

        if ($context === null) {
            return;
        }

        $statusCode = $event->getResponse()->getStatusCode();
        $this->tracingService->finishSpan($context, $statusCode);
    }
}
