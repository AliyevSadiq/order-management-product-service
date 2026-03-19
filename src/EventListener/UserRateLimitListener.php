<?php

namespace App\EventListener;

use App\RateLimit\RateLimitService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class UserRateLimitListener
{
    public function __construct(
        private RateLimitService $rateLimitService,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $userId = $request->attributes->get('auth_user_id');

        if ($userId === null) {
            return;
        }

        $result = $this->rateLimitService->consume($userId);

        $request->attributes->set('rate_limit_remaining', $result->remaining);
        $request->attributes->set('rate_limit_limit', $result->limit);

        if (!$result->allowed) {
            $response = new JsonResponse(
                ['error' => 'Too many requests. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );

            $response->headers->set('Retry-After', (string) $result->retryAfter);
            $response->headers->set('X-RateLimit-Limit', (string) $result->limit);
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('X-RateLimit-Reset', (string) (time() + $result->retryAfter));

            $event->setResponse($response);
        }
    }
}
