<?php

namespace App\Message;

final readonly class ProductDeleted
{
    public function __construct(
        public string $productId,
    ) {
    }
}
