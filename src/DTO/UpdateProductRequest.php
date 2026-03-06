<?php

namespace App\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(schema: 'UpdateProductRequest')]
final readonly class UpdateProductRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 255, minMessage: 'Name must be at least {{ limit }} characters.', maxMessage: 'Name cannot exceed {{ limit }} characters.')]
        #[OA\Property(example: 'Updated Keyboard Name', nullable: true)]
        public ?string $name = null,

        #[Assert\Length(min: 10, minMessage: 'Description must be at least {{ limit }} characters.')]
        #[OA\Property(example: 'Updated product description with more details', nullable: true)]
        public ?string $description = null,

        #[Assert\Positive(message: 'Price must be a positive number.')]
        #[OA\Property(example: 59.99, nullable: true)]
        public ?float $price = null,

        #[Assert\PositiveOrZero(message: 'Stock must be zero or a positive number.')]
        #[OA\Property(example: 150, nullable: true)]
        public ?int $stock = null,

        #[Assert\NotNull(message: 'Category ID is required.')]
        #[Assert\Uuid(message: 'Category ID must be a valid UUID.')]
        #[OA\Property(example: '550e8400-e29b-41d4-a716-446655440000', nullable: true)]
        public ?string $categoryId = null,

        #[OA\Property(example: true, nullable: true)]
        public ?bool $active = null,
    ) {
    }
}
