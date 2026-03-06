<?php

namespace App\Service;

use App\Contract\CategoryServiceInterface;
use App\DTO\CreateCategoryRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private CacheInterface $cache,
    ) {
    }

    public function create(CreateCategoryRequest $request): Category
    {
        $existing = $this->categoryRepository->findBySlug($request->slug);
        if ($existing !== null) {
            throw new \RuntimeException(sprintf('Category with slug "%s" already exists.', $request->slug));
        }

        $category = new Category();
        $category->setName($request->name);
        $category->setSlug($request->slug);

        if ($request->description !== null) {
            $category->setDescription($request->description);
        }

        if ($request->parentId !== null) {
            $category->setParentId(Uuid::fromString($request->parentId));
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $this->cache->delete('categories_list');

        return $category;
    }

    public function findById(string $id): Category
    {
        return $this->cache->get('category_' . $id, function (ItemInterface $item) use ($id): Category {
            $item->expiresAfter(7200);

            $category = $this->categoryRepository->find(Uuid::fromString($id));

            if ($category === null) {
                throw new \RuntimeException(sprintf('Category with ID "%s" not found.', $id));
            }

            return $category;
        });
    }

    public function findAll(): array
    {
        return $this->categoryRepository->findBy([], ['name' => 'ASC']);
    }
}
