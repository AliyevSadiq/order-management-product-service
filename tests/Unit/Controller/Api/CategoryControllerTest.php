<?php

namespace App\Tests\Unit\Controller\Api;

use App\Contract\CategoryServiceInterface;
use App\Controller\Api\CategoryController;
use App\Entity\Category;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class CategoryControllerTest extends TestCase
{
    private CategoryServiceInterface&MockObject $categoryService;
    private CategoryController $controller;

    protected function setUp(): void
    {
        $this->categoryService = $this->createMock(CategoryServiceInterface::class);
        $this->controller = new CategoryController($this->categoryService);

        $container = new Container();
        $this->controller->setContainer($container);
    }

    public function testListReturnsCategories(): void
    {
        $category = $this->createCategory('Electronics', 'electronics');

        $this->categoryService->expects(self::once())
            ->method('findAll')
            ->willReturn([$category]);

        $response = $this->controller->list();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertCount(1, $content);
        self::assertSame('Electronics', $content[0]['name']);
        self::assertSame('electronics', $content[0]['slug']);
    }

    public function testListReturnsEmptyArray(): void
    {
        $this->categoryService->method('findAll')->willReturn([]);

        $response = $this->controller->list();

        $content = json_decode($response->getContent(), true);
        self::assertCount(0, $content);
    }

    public function testCreateReturnsCreatedOnSuccess(): void
    {
        $category = $this->createCategory('Electronics', 'electronics');

        $this->categoryService->expects(self::once())
            ->method('create')
            ->willReturn($category);

        $request = new Request(content: json_encode([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic devices',
        ]));

        $response = $this->controller->create($request);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Electronics', $content['name']);
    }

    public function testCreateReturnsBadRequestWhenNameMissing(): void
    {
        $request = new Request(content: json_encode(['slug' => 'electronics']));

        $response = $this->controller->create($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Name and slug are required.', $content['error']);
    }

    public function testCreateReturnsBadRequestWhenSlugMissing(): void
    {
        $request = new Request(content: json_encode(['name' => 'Electronics']));

        $response = $this->controller->create($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testCreateReturnsBadRequestOnDuplicateSlug(): void
    {
        $this->categoryService->method('create')
            ->willThrowException(new \RuntimeException('Category with slug "electronics" already exists.'));

        $request = new Request(content: json_encode([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]));

        $response = $this->controller->create($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertStringContainsString('already exists', $content['error']);
    }

    public function testShowReturnsCategory(): void
    {
        $category = $this->createCategory('Electronics', 'electronics');
        $categoryId = (string) $category->getId();

        $this->categoryService->expects(self::once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($category);

        $response = $this->controller->show($categoryId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Electronics', $content['name']);
    }

    public function testShowReturnsNotFoundOnRuntimeException(): void
    {
        $categoryId = (string) Uuid::v7();

        $this->categoryService->method('findById')
            ->willThrowException(new \RuntimeException('Category not found'));

        $response = $this->controller->show($categoryId);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Category not found', $content['error']);
    }

    private function createCategory(string $name, string $slug): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);

        $reflection = new \ReflectionProperty(Category::class, 'id');
        $reflection->setValue($category, Uuid::v7());

        return $category;
    }
}
