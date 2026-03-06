<?php

namespace App\Contract;

use App\DTO\CreateProductRequest;
use App\DTO\ProductResponse;
use App\DTO\UpdateProductRequest;

interface ProductServiceInterface
{
    public function create(CreateProductRequest $request): ProductResponse;

    public function update(string $id, UpdateProductRequest $request): ProductResponse;

    public function delete(string $id): void;

    public function findById(string $id): ProductResponse;

    public function findAll(int $page = 1, int $limit = 20): array;
}
