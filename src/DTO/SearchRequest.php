<?php

namespace App\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(schema: 'SearchRequest')]
final readonly class SearchRequest
{
    public function __construct(
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Property(example: 'keyboard', nullable: true)]
        public ?string $query = null,

        #[Assert\Uuid(message: 'Category ID must be a valid UUID.')]
        #[OA\Property(example: '550e8400-e29b-41d4-a716-446655440000', nullable: true)]
        public ?string $categoryId = null,

        #[Assert\PositiveOrZero(message: 'Minimum price must be zero or positive.')]
        #[OA\Property(example: 10.0, nullable: true)]
        public ?float $minPrice = null,

        #[Assert\Positive(message: 'Maximum price must be positive.')]
        #[OA\Property(example: 100.0, nullable: true)]
        public ?float $maxPrice = null,

        #[Assert\Positive(message: 'Page must be a positive number.')]
        #[OA\Property(example: 1)]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100, notInRangeMessage: 'Limit must be between {{ min }} and {{ max }}.')]
        #[OA\Property(example: 20)]
        public int $limit = 20,
    ) {
    }
}
