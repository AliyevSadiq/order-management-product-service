<?php

namespace App\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(schema: 'CreateProductRequest', required: ['name', 'description', 'price', 'sku', 'stock'])]
final readonly class CreateProductRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Product name is required.')]
        #[Assert\Length(min: 2, max: 255, minMessage: 'Name must be at least {{ limit }} characters.', maxMessage: 'Name cannot exceed {{ limit }} characters.')]
        #[OA\Property(example: 'Wireless Keyboard', minLength: 2, maxLength: 255)]
        public string $name,

        #[Assert\NotBlank(message: 'Product description is required.')]
        #[Assert\Length(min: 10, minMessage: 'Description must be at least {{ limit }} characters.')]
        #[OA\Property(example: 'A high-quality wireless keyboard with Bluetooth connectivity')]
        public string $description,

        #[Assert\NotBlank(message: 'Product price is required.')]
        #[Assert\Positive(message: 'Price must be a positive number.')]
        #[OA\Property(example: 49.99)]
        public float $price,

        #[Assert\NotBlank(message: 'SKU is required.')]
        #[Assert\Length(min: 3, max: 100, minMessage: 'SKU must be at least {{ limit }} characters.', maxMessage: 'SKU cannot exceed {{ limit }} characters.')]
        #[OA\Property(example: 'KB-WIRELESS-001')]
        public string $sku,

        #[Assert\NotNull(message: 'Stock is required.')]
        #[Assert\PositiveOrZero(message: 'Stock must be zero or a positive number.')]
        #[OA\Property(example: 100)]
        public int $stock,

        #[Assert\NotNull(message: 'Category ID is required.')]
        #[Assert\Uuid(message: 'Category ID must be a valid UUID.')]
        #[OA\Property(example: '550e8400-e29b-41d4-a716-446655440000', nullable: true)]
        public ?string $categoryId = null,
    ) {
    }
}
