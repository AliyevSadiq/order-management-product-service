<?php

namespace App\Service;

use App\DTO\CreateProductRequest;
use App\DTO\ProductResponse;
use App\DTO\UpdateProductRequest;
use App\Entity\Product;
use App\Message\ProductCreated;
use App\Message\ProductDeleted;
use App\Message\ProductUpdated;
use App\Contract\ProductServiceInterface;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

readonly class ProductService implements ProductServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private MessageBusInterface $messageBus,
        private ProductCacheManager $cacheManager,
    ) {
    }

    public function create(CreateProductRequest $request): ProductResponse
    {
        $existingProduct = $this->productRepository->findOneBy(['sku' => $request->sku]);
        if ($existingProduct !== null) {
            throw new \RuntimeException(sprintf('A product with SKU "%s" already exists.', $request->sku));
        }

        if ($request->categoryId !== null) {
            $category = $this->categoryRepository->find(Uuid::fromString($request->categoryId));
            if ($category === null) {
                throw new \RuntimeException(sprintf('Category with ID "%s" not found.', $request->categoryId));
            }
        }

        $product = new Product();
        $product->setName($request->name);
        $product->setDescription($request->description);
        $product->setPrice(number_format($request->price, 2, '.', ''));
        $product->setSku($request->sku);
        $product->setStock($request->stock);

        if ($request->categoryId !== null) {
            $product->setCategoryId(Uuid::fromString($request->categoryId));
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->cacheManager->invalidate('products_list');

        $this->messageBus->dispatch(new ProductCreated(
            productId: (string) $product->getId(),
            name: $product->getName(),
            price: $product->getPrice(),
            sku: $product->getSku(),
        ));

        return ProductResponse::fromEntity($product);
    }

    public function update(string $id, UpdateProductRequest $request): ProductResponse
    {
        $product = $this->productRepository->find(Uuid::fromString($id));

        if ($product === null) {
            throw new \RuntimeException(sprintf('Product with ID "%s" not found.', $id));
        }

        $changedFields = [];

        if ($request->name !== null) {
            $product->setName($request->name);
            $changedFields[] = 'name';
        }

        if ($request->description !== null) {
            $product->setDescription($request->description);
            $changedFields[] = 'description';
        }

        if ($request->price !== null) {
            $product->setPrice(number_format($request->price, 2, '.', ''));
            $changedFields[] = 'price';
        }

        if ($request->stock !== null) {
            $product->setStock($request->stock);
            $changedFields[] = 'stock';
        }

        if ($request->categoryId !== null) {
            $category = $this->categoryRepository->find(Uuid::fromString($request->categoryId));
            if ($category === null) {
                throw new \RuntimeException(sprintf('Category with ID "%s" not found.', $request->categoryId));
            }
            $product->setCategoryId(Uuid::fromString($request->categoryId));
            $changedFields[] = 'categoryId';
        }

        if ($request->active !== null) {
            $product->setActive($request->active);
            $changedFields[] = 'active';
        }

        $this->entityManager->flush();

        $this->cacheManager->invalidate('product_' . $id, 'products_list');

        if (count($changedFields) > 0) {
            $this->messageBus->dispatch(new ProductUpdated(
                productId: (string) $product->getId(),
                changedFields: $changedFields,
                name: $product->getName(),
                price: $product->getPrice(),
            ));
        }

        return ProductResponse::fromEntity($product);
    }

    public function delete(string $id): void
    {
        $product = $this->productRepository->find(Uuid::fromString($id));

        if ($product === null) {
            throw new \RuntimeException(sprintf('Product with ID "%s" not found.', $id));
        }

        $productId = (string) $product->getId();

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        $this->cacheManager->invalidate('product_' . $id, 'products_list');

        $this->messageBus->dispatch(new ProductDeleted(
            productId: $productId,
        ));
    }

    public function findById(string $id): ProductResponse
    {
        return $this->cacheManager->get('product_' . $id, function () use ($id): ProductResponse {
            $product = $this->productRepository->find(Uuid::fromString($id));

            if ($product === null) {
                throw new \RuntimeException(sprintf('Product with ID "%s" not found.', $id));
            }

            return ProductResponse::fromEntity($product);
        });
    }

    public function findAll(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $products = $this->productRepository->findBy(
            criteria: [],
            orderBy: ['createdAt' => 'DESC'],
            limit: $limit,
            offset: $offset,
        );

        $total = $this->productRepository->count([]);

        $data = array_map(
            static fn(Product $product): ProductResponse => ProductResponse::fromEntity($product),
            $products,
        );

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
