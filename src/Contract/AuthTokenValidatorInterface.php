<?php

namespace App\Contract;

interface AuthTokenValidatorInterface
{
    public function validateToken(string $token): ?array;
}
