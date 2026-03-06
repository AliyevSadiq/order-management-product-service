<?php

namespace App\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class ServiceNameProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: array_merge($record->extra, [
            'service' => 'product-service',
        ]));
    }
}
