<?php

namespace App\Message;

final readonly class ProductCreated
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $price,
        public string $sku,
    ) {
    }
}
