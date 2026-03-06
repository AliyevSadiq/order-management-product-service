<?php

namespace App\Contract;

use App\DTO\CreateCategoryRequest;
use App\Entity\Category;

interface CategoryServiceInterface
{
    public function create(CreateCategoryRequest $request): Category;

    public function findById(string $id): Category;

    public function findAll(): array;
}
