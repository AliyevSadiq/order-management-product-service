<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\StockReservation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class StockReservationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<array{productId: string, quantity: int}> $items
     */
    public function reserveStock(array $items): StockReservation
    {
        $this->entityManager->beginTransaction();

        try {
            foreach ($items as $item) {
                $product = $this->entityManager
                    ->getRepository(Product::class)
                    ->createQueryBuilder('p')
                    ->where('p.id = :id')
                    ->setParameter('id', Uuid::fromString($item['productId']), 'uuid')
                    ->getQuery()
                    ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
                    ->getOneOrNullResult();

                if ($product === null) {
                    throw new \RuntimeException(sprintf('Product "%s" not found.', $item['productId']));
                }

                if ($product->getStock() < $item['quantity']) {
                    throw new \RuntimeException(sprintf(
                        'Insufficient stock for product "%s". Available: %d, Requested: %d',
                        $product->getName(),
                        $product->getStock(),
                        $item['quantity'],
                    ));
                }

                $product->setStock($product->getStock() - $item['quantity']);
            }

            $reservation = new StockReservation($items);
            $this->entityManager->persist($reservation);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Stock reserved', [
                'reservation_id' => (string) $reservation->getId(),
                'items_count' => count($items),
            ]);

            return $reservation;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function confirmStock(string $reservationId): void
    {
        $reservation = $this->entityManager->find(StockReservation::class, Uuid::fromString($reservationId));

        if ($reservation === null) {
            throw new \RuntimeException(sprintf('Reservation "%s" not found.', $reservationId));
        }

        if ($reservation->getStatus() !== StockReservation::STATUS_RESERVED) {
            throw new \RuntimeException(sprintf('Reservation "%s" is not in reserved state.', $reservationId));
        }

        $reservation->setStatus(StockReservation::STATUS_CONFIRMED);
        $this->entityManager->flush();

        $this->logger->info('Stock reservation confirmed', [
            'reservation_id' => $reservationId,
        ]);
    }

    public function releaseStock(string $reservationId): void
    {
        $reservation = $this->entityManager->find(StockReservation::class, Uuid::fromString($reservationId));

        if ($reservation === null) {
            throw new \RuntimeException(sprintf('Reservation "%s" not found.', $reservationId));
        }

        if ($reservation->getStatus() !== StockReservation::STATUS_RESERVED) {
            throw new \RuntimeException(sprintf('Reservation "%s" is not in reserved state.', $reservationId));
        }

        $this->entityManager->beginTransaction();

        try {
            foreach ($reservation->getItems() as $item) {
                $product = $this->entityManager
                    ->getRepository(Product::class)
                    ->createQueryBuilder('p')
                    ->where('p.id = :id')
                    ->setParameter('id', Uuid::fromString($item['productId']), 'uuid')
                    ->getQuery()
                    ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
                    ->getOneOrNullResult();

                if ($product !== null) {
                    $product->setStock($product->getStock() + $item['quantity']);
                }
            }

            $reservation->setStatus(StockReservation::STATUS_RELEASED);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Stock reservation released', [
                'reservation_id' => $reservationId,
            ]);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
