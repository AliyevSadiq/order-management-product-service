<?php

namespace App\Message;

final readonly class ProductUpdated
{
    public function __construct(
        public string $productId,
        public array $changedFields,
        public string $name,
        public string $price,
    ) {
    }
}
