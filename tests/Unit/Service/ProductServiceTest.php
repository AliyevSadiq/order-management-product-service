<?php

namespace App\Tests\Unit\Service;

use App\DTO\CreateProductRequest;
use App\DTO\ProductResponse;
use App\DTO\UpdateProductRequest;
use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\ProductCacheManager;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class ProductServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ProductRepository&MockObject $productRepository;
    private CategoryRepository&MockObject $categoryRepository;
    private MessageBusInterface&MockObject $messageBus;
    private ProductCacheManager&MockObject $cacheManager;
    private ProductService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->cacheManager = $this->createMock(ProductCacheManager::class);

        $this->service = new ProductService(
            $this->entityManager,
            $this->productRepository,
            $this->categoryRepository,
            $this->messageBus,
            $this->cacheManager,
        );
    }

    public function testCreateProductSuccessfully(): void
    {
        $categoryId = (string) Uuid::v7();
        $request = new CreateProductRequest(
            name: 'Wireless Keyboard',
            description: 'A high-quality wireless keyboard with Bluetooth',
            price: 49.99,
            sku: 'KB-001',
            stock: 100,
            categoryId: $categoryId,
        );

        $this->productRepository->method('findOneBy')->willReturn(null);
        $this->categoryRepository->method('find')->willReturn(new Category());
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');
        $this->cacheManager->expects(self::once())->method('invalidate');
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(fn($msg) => new Envelope($msg));

        $result = $this->service->create($request);

        self::assertInstanceOf(ProductResponse::class, $result);
        self::assertSame('Wireless Keyboard', $result->name);
        self::assertSame('49.99', $result->price);
        self::assertSame('KB-001', $result->sku);
        self::assertSame(100, $result->stock);
    }

    public function testCreateProductThrowsOnDuplicateSku(): void
    {
        $existing = $this->createProduct();

        $this->productRepository->method('findOneBy')
            ->with(['sku' => 'KB-001'])
            ->willReturn($existing);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A product with SKU "KB-001" already exists.');

        $request = new CreateProductRequest(
            name: 'Test', description: 'Test description here', price: 10.00,
            sku: 'KB-001', stock: 5,
        );
        $this->service->create($request);
    }

    public function testCreateProductThrowsOnInvalidCategory(): void
    {
        $categoryId = (string) Uuid::v7();

        $this->productRepository->method('findOneBy')->willReturn(null);
        $this->categoryRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Category with ID "%s" not found.', $categoryId));

        $request = new CreateProductRequest(
            name: 'Test', description: 'Test description here', price: 10.00,
            sku: 'KB-002', stock: 5, categoryId: $categoryId,
        );
        $this->service->create($request);
    }

    public function testUpdateProductSuccessfully(): void
    {
        $product = $this->createProduct();
        $productId = (string) $product->getId();

        $this->productRepository->method('find')->willReturn($product);
        $this->entityManager->expects(self::once())->method('flush');
        $this->cacheManager->expects(self::once())->method('invalidate');
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(fn($msg) => new Envelope($msg));

        $request = new UpdateProductRequest(name: 'Updated Keyboard', price: 59.99);
        $result = $this->service->update($productId, $request);

        self::assertSame('Updated Keyboard', $result->name);
        self::assertSame('59.99', $result->price);
    }

    public function testUpdateProductThrowsWhenNotFound(): void
    {
        $productId = (string) Uuid::v7();
        $this->productRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Product with ID "%s" not found.', $productId));

        $request = new UpdateProductRequest(name: 'Updated');
        $this->service->update($productId, $request);
    }

    public function testUpdateProductWithCategoryValidation(): void
    {
        $product = $this->createProduct();
        $productId = (string) $product->getId();
        $categoryId = (string) Uuid::v7();

        $this->productRepository->method('find')->willReturn($product);
        $this->categoryRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Category with ID "%s" not found.', $categoryId));

        $request = new UpdateProductRequest(categoryId: $categoryId);
        $this->service->update($productId, $request);
    }

    public function testUpdateProductNoChangesSkipsDispatch(): void
    {
        $product = $this->createProduct();
        $productId = (string) $product->getId();

        $this->productRepository->method('find')->willReturn($product);
        $this->entityManager->expects(self::once())->method('flush');
        $this->messageBus->expects(self::never())->method('dispatch');

        $request = new UpdateProductRequest();
        $this->service->update($productId, $request);
    }

    public function testDeleteProductSuccessfully(): void
    {
        $product = $this->createProduct();
        $productId = (string) $product->getId();

        $this->productRepository->method('find')->willReturn($product);
        $this->entityManager->expects(self::once())->method('remove')->with($product);
        $this->entityManager->expects(self::once())->method('flush');
        $this->cacheManager->expects(self::once())->method('invalidate');
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(fn($msg) => new Envelope($msg));

        $this->service->delete($productId);
    }

    public function testDeleteProductThrowsWhenNotFound(): void
    {
        $productId = (string) Uuid::v7();
        $this->productRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Product with ID "%s" not found.', $productId));

        $this->service->delete($productId);
    }

    public function testFindByIdDelegatesToCacheManager(): void
    {
        $product = $this->createProduct();
        $productId = (string) $product->getId();
        $response = ProductResponse::fromEntity($product);

        $this->cacheManager->expects(self::once())
            ->method('get')
            ->with('product_' . $productId, self::isType('callable'))
            ->willReturn($response);

        $result = $this->service->findById($productId);

        self::assertSame($response, $result);
    }

    public function testFindAllReturnsPaginatedResults(): void
    {
        $product = $this->createProduct();

        $this->productRepository->expects(self::once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 10, 0)
            ->willReturn([$product]);

        $this->productRepository->expects(self::once())
            ->method('count')
            ->willReturn(1);

        $result = $this->service->findAll(1, 10);

        self::assertCount(1, $result['data']);
        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['page']);
        self::assertSame(10, $result['limit']);
        self::assertInstanceOf(ProductResponse::class, $result['data'][0]);
    }

    public function testFindAllPage2CalculatesOffset(): void
    {
        $this->productRepository->expects(self::once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 20, 20)
            ->willReturn([]);

        $this->productRepository->method('count')->willReturn(0);

        $result = $this->service->findAll(2, 20);

        self::assertCount(0, $result['data']);
        self::assertSame(2, $result['page']);
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setName('Wireless Keyboard');
        $product->setDescription('A high-quality wireless keyboard');
        $product->setPrice('49.99');
        $product->setSku('KB-001');
        $product->setStock(100);

        $reflection = new \ReflectionProperty(Product::class, 'id');
        $reflection->setValue($product, Uuid::v7());

        return $product;
    }
}
