<?php

namespace App\Controller\Api;

use App\Contract\SearchServiceInterface;
use App\DTO\SearchRequest;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Search')]
#[Route('/api')]
final class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchServiceInterface $searchService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[OA\Get(
        path: '/api/search',
        summary: 'Search products using Elasticsearch',
        tags: ['Search'],
        parameters: [
            new OA\Parameter(name: 'query', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'keyboard'),
            new OA\Parameter(name: 'categoryId', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'minPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'maxPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Search results'),
            new OA\Response(response: 400, description: 'Validation errors'),
            new OA\Response(response: 503, description: 'Search service unavailable'),
        ]
    )]
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $searchRequest = new SearchRequest(
            query: $request->query->get('query'),
            categoryId: $request->query->get('categoryId'),
            minPrice: $request->query->has('minPrice') ? (float) $request->query->get('minPrice') : null,
            maxPrice: $request->query->has('maxPrice') ? (float) $request->query->get('maxPrice') : null,
            page: $request->query->getInt('page', 1),
            limit: $request->query->getInt('limit', 20),
        );

        $violations = $this->validator->validate($searchRequest);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $results = $this->searchService->search($searchRequest);

            return $this->json($results, Response::HTTP_OK);
        } catch (\Throwable $e) {
            return $this->json(
                ['error' => 'Search service is currently unavailable.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
