<?php

namespace App\Contract;

use App\DTO\SearchRequest;
use App\Entity\Product;

interface SearchServiceInterface
{
    public function search(SearchRequest $request): array;

    public function indexProduct(Product $product): void;

    public function removeProduct(string $id): void;
}
