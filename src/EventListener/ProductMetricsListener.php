<?php

namespace App\EventListener;

use App\Contract\MetricsRegistryInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class ProductMetricsListener
{
    public function __construct(
        private readonly MetricsRegistryInterface $metricsRegistry,
    ) {}

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

        try {
            $registry = $this->metricsRegistry->getRegistry();

            if ($route === 'product_create' && $method === 'POST' && $status === '201') {
                $registry->getOrRegisterCounter(
                    '', 'products_created_total', 'Total products created', []
                )->inc();
            }

            if ($route === 'product_update' && $method === 'PUT' && $status === '200') {
                $registry->getOrRegisterCounter(
                    '', 'products_updated_total', 'Total products updated', []
                )->inc();
            }

            if ($route === 'product_delete' && $method === 'DELETE' && $status === '204') {
                $registry->getOrRegisterCounter(
                    '', 'products_deleted_total', 'Total products deleted', []
                )->inc();
            }

            if ($route === 'product_list' && $status === '200') {
                $registry->getOrRegisterCounter(
                    '', 'products_listed_total', 'Total product list views', []
                )->inc();
            }

            if ($route === 'product_show' && $status === '200') {
                $registry->getOrRegisterCounter(
                    '', 'products_viewed_total', 'Total individual product views', []
                )->inc();
            }

            if ($route === 'search' && $method === 'GET') {
                $registry->getOrRegisterCounter(
                    '', 'product_search_queries_total', 'Total product search queries', ['status']
                )->inc([$status]);
            }

            if ($route === 'category_create' && $status === '201') {
                $registry->getOrRegisterCounter(
                    '', 'categories_created_total', 'Total categories created', []
                )->inc();
            }
        } catch (\Throwable) {
        }
    }
}
