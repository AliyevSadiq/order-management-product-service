<?php

namespace App\Contract;

use Prometheus\CollectorRegistry;

interface MetricsRegistryInterface
{
    public function getRegistry(): CollectorRegistry;
}
