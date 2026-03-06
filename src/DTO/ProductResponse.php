<?php

namespace App\DTO;

use App\Entity\Product;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ProductResponse')]
final readonly class ProductResponse
{
    public function __construct(
        #[OA\Property(example: '550e8400-e29b-41d4-a716-446655440000')]
        public string $id,
        #[OA\Property(example: 'Wireless Keyboard')]
        public string $name,
        #[OA\Property(example: 'A high-quality wireless keyboard')]
        public string $description,
        #[OA\Property(example: '49.99')]
        public string $price,
        #[OA\Property(example: 'KB-WIRELESS-001')]
        public string $sku,
        #[OA\Property(example: 100)]
        public int $stock,
        #[OA\Property(example: '550e8400-e29b-41d4-a716-446655440000', nullable: true)]
        public ?string $categoryId,
        #[OA\Property(example: true)]
        public bool $active,
        #[OA\Property(example: '2024-01-15T10:30:00+00:00')]
        public string $createdAt,
        #[OA\Property(example: '2024-01-15T10:30:00+00:00')]
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: (string) $product->getId(),
            name: $product->getName(),
            description: $product->getDescription(),
            price: $product->getPrice(),
            sku: $product->getSku(),
            stock: $product->getStock(),
            categoryId: $product->getCategoryId() ? (string) $product->getCategoryId() : null,
            active: $product->isActive(),
            createdAt: $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $product->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
