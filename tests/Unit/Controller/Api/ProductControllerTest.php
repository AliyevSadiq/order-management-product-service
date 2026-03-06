<?php

namespace App\Tests\Unit\Controller\Api;

use App\Contract\ProductServiceInterface;
use App\Controller\Api\ProductController;
use App\DTO\ProductResponse;
use App\DTO\UpdateProductRequest;
use App\Service\RequestValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ProductControllerTest extends TestCase
{
    private ProductServiceInterface&MockObject $productService;
    private RequestValidator&MockObject $requestValidator;
    private ProductController $controller;

    protected function setUp(): void
    {
        $this->productService = $this->createMock(ProductServiceInterface::class);
        $this->requestValidator = $this->createMock(RequestValidator::class);
        $this->controller = new ProductController($this->productService, $this->requestValidator);

        $container = new Container();
        $this->controller->setContainer($container);
    }

    public function testListReturnsPaginatedProducts(): void
    {
        $this->productService->expects(self::once())
            ->method('findAll')
            ->with(1, 20)
            ->willReturn([
                'data' => [],
                'total' => 0,
                'page' => 1,
                'limit' => 20,
            ]);

        $request = new Request(query: ['page' => '1', 'limit' => '20']);
        $response = $this->controller->list($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testListClampsPageAndLimit(): void
    {
        $this->productService->expects(self::once())
            ->method('findAll')
            ->with(1, 100);

        $request = new Request(query: ['page' => '-5', 'limit' => '999']);
        $this->controller->list($request);
    }

    public function testCreateReturnsCreatedOnSuccess(): void
    {
        $productResponse = $this->createProductResponse();

        $this->requestValidator->method('validate')->willReturn(null);
        $this->productService->expects(self::once())
            ->method('create')
            ->willReturn($productResponse);

        $request = new Request(content: json_encode([
            'name' => 'Keyboard',
            'description' => 'A great wireless keyboard',
            'price' => 49.99,
            'sku' => 'KB-001',
            'stock' => 100,
        ]));

        $response = $this->controller->create($request);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateReturnsValidationErrors(): void
    {
        $validationResponse = new JsonResponse(
            ['errors' => ['name' => 'Product name is required.']],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $this->requestValidator->method('validate')->willReturn($validationResponse);

        $request = new Request(content: json_encode(['name' => '', 'sku' => '']));

        $response = $this->controller->create($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testShowReturnsProduct(): void
    {
        $productResponse = $this->createProductResponse();
        $productId = $productResponse->id;

        $this->productService->expects(self::once())
            ->method('findById')
            ->with($productId)
            ->willReturn($productResponse);

        $response = $this->controller->show($productId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testShowReturnsNotFoundOnRuntimeException(): void
    {
        $productId = (string) Uuid::v7();

        $this->productService->method('findById')
            ->willThrowException(new \RuntimeException('Product not found'));

        $response = $this->controller->show($productId);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Product not found', $content['error']);
    }

    public function testUpdateReturnsUpdatedProduct(): void
    {
        $productResponse = $this->createProductResponse();
        $productId = $productResponse->id;

        $this->requestValidator->method('validate')->willReturn(null);
        $this->productService->expects(self::once())
            ->method('update')
            ->willReturn($productResponse);

        $request = new Request(content: json_encode(['name' => 'Updated Name']));

        $response = $this->controller->update($productId, $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testUpdateReturnsValidationErrors(): void
    {
        $validationResponse = new JsonResponse(
            ['errors' => ['price' => 'Price must be a positive number.']],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $this->requestValidator->method('validate')->willReturn($validationResponse);

        $request = new Request(content: json_encode(['price' => -5]));

        $response = $this->controller->update((string) Uuid::v7(), $request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testUpdateReturnsNotFoundOnRuntimeException(): void
    {
        $this->requestValidator->method('validate')->willReturn(null);
        $this->productService->method('update')
            ->willThrowException(new \RuntimeException('Product not found'));

        $request = new Request(content: json_encode(['name' => 'Test']));

        $response = $this->controller->update((string) Uuid::v7(), $request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteReturnsNoContent(): void
    {
        $productId = (string) Uuid::v7();

        $this->productService->expects(self::once())
            ->method('delete')
            ->with($productId);

        $response = $this->controller->delete($productId);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testDeleteReturnsNotFoundOnRuntimeException(): void
    {
        $this->productService->method('delete')
            ->willThrowException(new \RuntimeException('Product not found'));

        $response = $this->controller->delete((string) Uuid::v7());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function createProductResponse(): ProductResponse
    {
        return new ProductResponse(
            id: (string) Uuid::v7(),
            name: 'Wireless Keyboard',
            description: 'A high-quality wireless keyboard',
            price: '49.99',
            sku: 'KB-001',
            stock: 100,
            categoryId: null,
            active: true,
            createdAt: '2024-01-15T10:30:00+00:00',
            updatedAt: '2024-01-15T10:30:00+00:00',
        );
    }
}
