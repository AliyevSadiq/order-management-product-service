<?php

namespace App\Tests\Unit\Service;

use App\Service\RequestValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestValidatorTest extends TestCase
{
    private ValidatorInterface&MockObject $validator;
    private RequestValidator $requestValidator;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->requestValidator = new RequestValidator($this->validator);
    }

    public function testValidateReturnsNullWhenNoViolations(): void
    {
        $dto = new \stdClass();

        $this->validator->expects(self::once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $result = $this->requestValidator->validate($dto);

        self::assertNull($result);
    }

    public function testValidateReturnsJsonResponseOnViolations(): void
    {
        $dto = new \stdClass();

        $violation1 = new ConstraintViolation('Name is required.', null, [], $dto, 'name', null);
        $violation2 = new ConstraintViolation('Price must be positive.', null, [], $dto, 'price', null);

        $this->validator->method('validate')
            ->willReturn(new ConstraintViolationList([$violation1, $violation2]));

        $result = $this->requestValidator->validate($dto);

        self::assertNotNull($result);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $result->getStatusCode());

        $content = json_decode($result->getContent(), true);
        self::assertArrayHasKey('errors', $content);
        self::assertSame('Name is required.', $content['errors']['name']);
        self::assertSame('Price must be positive.', $content['errors']['price']);
    }

    public function testValidateReturnsSingleViolation(): void
    {
        $dto = new \stdClass();

        $violation = new ConstraintViolation('SKU is required.', null, [], $dto, 'sku', null);

        $this->validator->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $result = $this->requestValidator->validate($dto);

        self::assertNotNull($result);
        $content = json_decode($result->getContent(), true);
        self::assertCount(1, $content['errors']);
        self::assertSame('SKU is required.', $content['errors']['sku']);
    }
}
