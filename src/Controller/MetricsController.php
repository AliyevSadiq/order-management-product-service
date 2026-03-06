<?php

namespace App\Controller;

use App\Contract\MetricsRegistryInterface;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MetricsController
{
    public function __construct(
        private MetricsRegistryInterface $metricsRegistry,
    ) {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function __invoke(): Response
    {
        $registry = $this->metricsRegistry->getRegistry();
        $renderer = new RenderTextFormat();

        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, Response::HTTP_OK, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
        ]);
    }
}
