<?php

namespace App\MessageHandler;

use App\Message\ProductCreated;
use App\Message\ProductDeleted;
use App\Message\ProductUpdated;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ProductEventLogger
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler]
    public function handleProductCreated(ProductCreated $message): void
    {
        $this->logger->info('Product created event received.', [
            'productId' => $message->productId,
            'name' => $message->name,
            'price' => $message->price,
            'sku' => $message->sku,
            'event' => 'product.created',
        ]);
    }

    #[AsMessageHandler]
    public function handleProductUpdated(ProductUpdated $message): void
    {
        $this->logger->info('Product updated event received.', [
            'productId' => $message->productId,
            'changedFields' => $message->changedFields,
            'event' => 'product.updated',
        ]);
    }

    #[AsMessageHandler]
    public function handleProductDeleted(ProductDeleted $message): void
    {
        $this->logger->info('Product deleted event received.', [
            'productId' => $message->productId,
            'event' => 'product.deleted',
        ]);
    }
}
