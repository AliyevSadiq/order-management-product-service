<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class TracingResponseListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $context = $request->attributes->get('trace_context');

        if ($context === null) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-Trace-Id', $context->traceId);
        $response->headers->set('X-Span-Id', $context->spanId);
    }
}
