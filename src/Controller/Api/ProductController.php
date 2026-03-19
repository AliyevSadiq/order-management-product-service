<?php

namespace App\Controller\Api;

use App\Contract\ProductServiceInterface;
use App\DTO\CreateProductRequest;
use App\DTO\ProductResponse;
use App\DTO\UpdateProductRequest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\RequestValidator;

#[OA\Tag(name: 'Products')]
#[Route('/api/v1/products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductServiceInterface $productService,
        private readonly RequestValidator $requestValidator,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/products',
        summary: 'List all products with pagination',
        security: [['BearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of products'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    #[Route('', name: 'product_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));

        $result = $this->productService->findAll($page, $limit);

        return $this->json($result, Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/api/v1/products',
        summary: 'Create a new product',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateProductRequest::class))),
        tags: ['Products'],
        responses: [
            new OA\Response(response: 201, description: 'Product created', content: new OA\JsonContent(ref: new Model(type: ProductResponse::class))),
            new OA\Response(response: 400, description: 'Validation errors'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    #[Route('', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $dto = new CreateProductRequest(
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            price: (float) ($data['price'] ?? 0),
            sku: $data['sku'] ?? '',
            stock: (int) ($data['stock'] ?? 0),
            categoryId: $data['categoryId'] ?? null,
        );

        $errorResponse = $this->requestValidator->validate($dto);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $product = $this->productService->create($dto);

        return $this->json($product, Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/v1/products/{id}',
        summary: 'Get a product by ID',
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Product details', content: new OA\JsonContent(ref: new Model(type: ProductResponse::class))),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    #[Route('/{id}', name: 'product_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $product = $this->productService->findById($id);

            return $this->json($product, Response::HTTP_OK);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[OA\Put(
        path: '/api/v1/products/{id}',
        summary: 'Update a product',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: UpdateProductRequest::class))),
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Product updated', content: new OA\JsonContent(ref: new Model(type: ProductResponse::class))),
            new OA\Response(response: 400, description: 'Validation errors'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    #[Route('/{id}', name: 'product_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $dto = new UpdateProductRequest(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            stock: isset($data['stock']) ? (int) $data['stock'] : null,
            categoryId: $data['categoryId'] ?? null,
            active: isset($data['active']) ? (bool) $data['active'] : null,
        );

        $errorResponse = $this->requestValidator->validate($dto);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $product = $this->productService->update($id, $dto);

            return $this->json($product, Response::HTTP_OK);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[OA\Delete(
        path: '/api/v1/products/{id}',
        summary: 'Delete a product',
        security: [['BearerAuth' => []]],
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, description: 'Product deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    #[Route('/{id}', name: 'product_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->productService->delete($id);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
