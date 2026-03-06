<?php

namespace App\EventListener;

use App\Contract\AuthTokenValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class AuthTokenListener
{
    public function __construct(
        private AuthTokenValidatorInterface $authServiceClient,
        private array $publicPrefixes,
        private array $protectedGetRoutes = [],
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach ($this->publicPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        if ($request->getMethod() === 'GET' && !$this->isProtectedGetRoute($path)) {
            return;
        }

        $authHeader = $request->headers->get('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Missing or invalid Authorization header.'],
                Response::HTTP_UNAUTHORIZED,
            ));
            return;
        }

        $token = substr($authHeader, 7);
        $userData = $this->authServiceClient->validateToken($token);

        if ($userData === null) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid or expired token.'],
                Response::HTTP_UNAUTHORIZED,
            ));
            return;
        }

        $request->attributes->set('auth_user_id', $userData['userId']);
        $request->attributes->set('auth_email', $userData['email']);
        $request->attributes->set('auth_full_name', trim($userData['first_name'] . ' ' . $userData['last_name']));
    }

    private function isProtectedGetRoute(string $path): bool
    {
        foreach ($this->protectedGetRoutes as $route) {
            if ($path === $route) {
                return true;
            }
        }

        return false;
    }
}
