<?php

namespace App\EventListener;

use App\Contract\MetricsRegistryInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class HttpMetricsListener
{
    private const SERVICE_NAME = 'product-service';

    public function __construct(
        private readonly MetricsRegistryInterface $metricsRegistry,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getRequest()->attributes->set('_metrics_start', microtime(true));
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $route = $request->attributes->get('_route', 'unknown');

        if ($route === 'metrics') {
            return;
        }

        $method = $request->getMethod();
        $status = (string) $response->getStatusCode();
        $startTime = $request->attributes->get('_metrics_start');

        try {
            $registry = $this->metricsRegistry->getRegistry();

            $counter = $registry->getOrRegisterCounter(
                '',
                'http_requests_total',
                'Total HTTP requests',
                ['service', 'method', 'endpoint', 'status']
            );
            $counter->inc([self::SERVICE_NAME, $method, $route, $status]);

            if ($startTime !== null) {
                $duration = microtime(true) - (float) $startTime;
                $histogram = $registry->getOrRegisterHistogram(
                    '',
                    'http_request_duration_seconds',
                    'HTTP request duration in seconds',
                    ['service', 'method', 'endpoint'],
                    [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
                );
                $histogram->observe($duration, [self::SERVICE_NAME, $method, $route]);
            }
        } catch (\Throwable) {
        }
    }
}
