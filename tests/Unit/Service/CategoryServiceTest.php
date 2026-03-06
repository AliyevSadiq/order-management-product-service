<?php

namespace App\Tests\Unit\Service;

use App\DTO\CreateCategoryRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CategoryServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CategoryRepository&MockObject $categoryRepository;
    private CacheInterface&MockObject $cache;
    private CategoryService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->service = new CategoryService(
            $this->entityManager,
            $this->categoryRepository,
            $this->cache,
        );
    }

    public function testCreateCategorySuccessfully(): void
    {
        $request = new CreateCategoryRequest(
            name: 'Electronics',
            slug: 'electronics',
            description: 'Electronic devices',
        );

        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('electronics')
            ->willReturn(null);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(Category::class));
        $this->entityManager->expects(self::once())->method('flush');

        $this->cache->expects(self::once())
            ->method('delete')
            ->with('categories_list');

        $category = $this->service->create($request);

        self::assertSame('Electronics', $category->getName());
        self::assertSame('electronics', $category->getSlug());
        self::assertSame('Electronic devices', $category->getDescription());
    }

    public function testCreateCategoryWithParentId(): void
    {
        $parentId = (string) Uuid::v7();
        $request = new CreateCategoryRequest(
            name: 'Laptops',
            slug: 'laptops',
            parentId: $parentId,
        );

        $this->categoryRepository->method('findBySlug')->willReturn(null);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');
        $this->cache->method('delete');

        $category = $this->service->create($request);

        self::assertSame('Laptops', $category->getName());
        self::assertSame($parentId, (string) $category->getParentId());
    }

    public function testCreateCategoryThrowsOnDuplicateSlug(): void
    {
        $existing = new Category();
        $existing->setName('Existing');
        $existing->setSlug('electronics');

        $this->categoryRepository->expects(self::once())
            ->method('findBySlug')
            ->with('electronics')
            ->willReturn($existing);

        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Category with slug "electronics" already exists.');

        $request = new CreateCategoryRequest(name: 'Electronics', slug: 'electronics');
        $this->service->create($request);
    }

    public function testCreateCategoryWithoutDescription(): void
    {
        $request = new CreateCategoryRequest(
            name: 'Books',
            slug: 'books',
        );

        $this->categoryRepository->method('findBySlug')->willReturn(null);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');
        $this->cache->method('delete');

        $category = $this->service->create($request);

        self::assertNull($category->getDescription());
    }

    public function testFindByIdReturnsCategoryFromCache(): void
    {
        $category = new Category();
        $category->setName('Electronics');
        $category->setSlug('electronics');
        $id = (string) Uuid::v7();

        $this->cache->expects(self::once())
            ->method('get')
            ->with('category_' . $id, self::isType('callable'))
            ->willReturn($category);

        $result = $this->service->findById($id);

        self::assertSame($category, $result);
    }

    public function testFindAllReturnsCategoriesFromRepository(): void
    {
        $cat1 = new Category();
        $cat1->setName('A');
        $cat1->setSlug('a');

        $cat2 = new Category();
        $cat2->setName('B');
        $cat2->setSlug('b');

        $this->categoryRepository->expects(self::once())
            ->method('findBy')
            ->with([], ['name' => 'ASC'])
            ->willReturn([$cat1, $cat2]);

        $result = $this->service->findAll();

        self::assertCount(2, $result);
        self::assertSame('A', $result[0]->getName());
        self::assertSame('B', $result[1]->getName());
    }
}
