<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCategoryRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Category name is required.')]
        #[Assert\Length(min: 2, max: 255, minMessage: 'Name must be at least {{ limit }} characters.', maxMessage: 'Name cannot exceed {{ limit }} characters.')]
        public string $name,

        #[Assert\NotBlank(message: 'Category slug is required.')]
        #[Assert\Length(min: 2, max: 255, minMessage: 'Slug must be at least {{ limit }} characters.', maxMessage: 'Slug cannot exceed {{ limit }} characters.')]
        #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Slug must contain only lowercase letters, numbers, and hyphens.')]
        public string $slug,

        #[Assert\Length(max: 1000, maxMessage: 'Description cannot exceed {{ limit }} characters.')]
        public ?string $description = null,

        #[Assert\Uuid(message: 'Parent ID must be a valid UUID.')]
        public ?string $parentId = null,
    ) {
    }
}
