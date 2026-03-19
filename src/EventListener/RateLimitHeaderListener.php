<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class RateLimitHeaderListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $remaining = $request->attributes->get('rate_limit_remaining');
        $limit = $request->attributes->get('rate_limit_limit');

        if ($remaining === null || $limit === null) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
    }
}
