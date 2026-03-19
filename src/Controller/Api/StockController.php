<?php

namespace App\Controller\Api;

use App\Service\StockReservationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Stock')]
#[Route('/api/v1/products')]
final class StockController extends AbstractController
{
    public function __construct(
        private readonly StockReservationService $stockReservationService,
    ) {}

    #[OA\Post(
        path: '/api/v1/products/reserve-stock',
        summary: 'Reserve stock for products',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'productId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'quantity', type: 'integer'),
                        ]),
                    ),
                ],
            ),
        ),
        tags: ['Stock'],
        responses: [
            new OA\Response(response: 200, description: 'Stock reserved'),
            new OA\Response(response: 400, description: 'Invalid request or insufficient stock'),
        ],
    )]
    #[Route('/reserve-stock', name: 'stock_reserve', methods: ['POST'])]
    public function reserveStock(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['items'])) {
            return $this->json(['error' => 'Invalid payload. "items" array is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $reservation = $this->stockReservationService->reserveStock($data['items']);

            return $this->json([
                'reservationId' => (string) $reservation->getId(),
                'status' => $reservation->getStatus(),
                'expiresAt' => $reservation->getExpiresAt()->format(\DateTimeInterface::ATOM),
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Post(
        path: '/api/v1/products/confirm-stock',
        summary: 'Confirm a stock reservation (permanently deduct stock)',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reservationId', type: 'string', format: 'uuid'),
                ],
            ),
        ),
        tags: ['Stock'],
        responses: [
            new OA\Response(response: 200, description: 'Stock confirmed'),
            new OA\Response(response: 400, description: 'Invalid reservation'),
        ],
    )]
    #[Route('/confirm-stock', name: 'stock_confirm', methods: ['POST'])]
    public function confirmStock(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['reservationId'])) {
            return $this->json(['error' => 'Invalid payload. "reservationId" is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->stockReservationService->confirmStock($data['reservationId']);

            return $this->json(['message' => 'Stock reservation confirmed successfully.']);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Post(
        path: '/api/v1/products/release-stock',
        summary: 'Release a stock reservation',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reservationId', type: 'string', format: 'uuid'),
                ],
            ),
        ),
        tags: ['Stock'],
        responses: [
            new OA\Response(response: 200, description: 'Stock released'),
            new OA\Response(response: 400, description: 'Invalid reservation'),
        ],
    )]
    #[Route('/release-stock', name: 'stock_release', methods: ['POST'])]
    public function releaseStock(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['reservationId'])) {
            return $this->json(['error' => 'Invalid payload. "reservationId" is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->stockReservationService->releaseStock($data['reservationId']);

            return $this->json(['message' => 'Stock reservation released successfully.']);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
