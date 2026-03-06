<?php

namespace App\Controller\Api;

use App\Contract\CategoryServiceInterface;
use App\DTO\CategoryResponse;
use App\DTO\CreateCategoryRequest;
use App\Entity\Category;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\RequestValidator;

#[OA\Tag(name: 'Categories')]
#[Route('/api/categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryServiceInterface $categoryService,
        private readonly RequestValidator $requestValidator,
    ) {
    }

    #[OA\Get(
        path: '/api/categories',
        summary: 'List all categories',
        tags: ['Categories'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of categories',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'string'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'parentId', type: 'string', nullable: true),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ]))
            ),
        ]
    )]
    #[Route('', name: 'category_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categoryService->findAll();

        $data = array_map(
            static fn(Category $category): array => CategoryResponse::fromEntity($category)->toArray(),
            $categories,
        );

        return $this->json($data, Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/api/categories',
        summary: 'Create a new category',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'slug'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Electronics'),
                    new OA\Property(property: 'slug', type: 'string', example: 'electronics'),
                    new OA\Property(property: 'description', type: 'string', example: 'Electronic devices and accessories', nullable: true),
                    new OA\Property(property: 'parentId', type: 'string', format: 'uuid', nullable: true),
                ]
            )
        ),
        tags: ['Categories'],
        responses: [
            new OA\Response(response: 201, description: 'Category created'),
            new OA\Response(response: 400, description: 'Name and slug are required'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 409, description: 'Slug already exists'),
        ]
    )]
    #[Route('', name: 'category_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (empty($data['name']) || empty($data['slug'])) {
            return $this->json(
                ['error' => 'Name and slug are required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $createRequest = new CreateCategoryRequest(
            name: $data['name'],
            slug: $data['slug'],
            description: $data['description'] ?? null,
            parentId: $data['parentId'] ?? null,
        );

        $errorResponse = $this->requestValidator->validate($createRequest);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $category = $this->categoryService->create($createRequest);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(
            CategoryResponse::fromEntity($category)->toArray(),
            Response::HTTP_CREATED,
        );
    }

    #[OA\Get(
        path: '/api/categories/{id}',
        summary: 'Get a category by ID',
        tags: ['Categories'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Category details'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    #[Route('/{id}', name: 'category_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $category = $this->categoryService->findById($id);

            return $this->json(
                CategoryResponse::fromEntity($category)->toArray(),
                Response::HTTP_OK,
            );
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
