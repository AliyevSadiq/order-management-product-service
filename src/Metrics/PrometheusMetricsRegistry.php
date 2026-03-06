<?php

namespace App\Metrics;

use App\Contract\MetricsRegistryInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APCng;

final class PrometheusMetricsRegistry implements MetricsRegistryInterface
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $this->registry = new CollectorRegistry(new APCng());
    }

    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }
}
