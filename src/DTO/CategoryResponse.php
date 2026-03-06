<?php

namespace App\DTO;

use App\Entity\Category;

final readonly class CategoryResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $parentId,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(Category $category): self
    {
        return new self(
            id: (string) $category->getId(),
            name: $category->getName(),
            slug: $category->getSlug(),
            description: $category->getDescription(),
            parentId: $category->getParentId() ? (string) $category->getParentId() : null,
            createdAt: $category->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parentId' => $this->parentId,
            'createdAt' => $this->createdAt,
        ];
    }
}
