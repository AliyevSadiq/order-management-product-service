<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class RequestValidator
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function validate(object $dto): ?JsonResponse
    {
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(
                ['errors' => $errors],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return null;
    }
}
