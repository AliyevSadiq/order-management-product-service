<?php

namespace App\EventListener;

use App\Contract\SearchServiceInterface;
use App\Entity\Product;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Psr\Log\LoggerInterface;

final readonly class ProductIndexListener
{
    public function __construct(
        private SearchServiceInterface $searchService,
        private LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Product) {
            return;
        }

        try {
            $this->searchService->indexProduct($entity);
            $this->logger->info('Product indexed in Elasticsearch.', [
                'productId' => (string) $entity->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to index product in Elasticsearch.', [
                'productId' => (string) $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Product) {
            return;
        }

        try {
            $this->searchService->indexProduct($entity);
            $this->logger->info('Product re-indexed in Elasticsearch.', [
                'productId' => (string) $entity->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to re-index product in Elasticsearch.', [
                'productId' => (string) $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Product) {
            return;
        }

        try {
            $this->searchService->removeProduct((string) $entity->getId());
            $this->logger->info('Product removed from Elasticsearch index.', [
                'productId' => (string) $entity->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to remove product from Elasticsearch.', [
                'productId' => (string) $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
