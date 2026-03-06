<?php

namespace App\Tests\Unit\Controller\Api;

use App\Contract\SearchServiceInterface;
use App\Controller\Api\SearchController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SearchControllerTest extends TestCase
{
    private SearchServiceInterface&MockObject $searchService;
    private ValidatorInterface&MockObject $validator;
    private SearchController $controller;

    protected function setUp(): void
    {
        $this->searchService = $this->createMock(SearchServiceInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->controller = new SearchController($this->searchService, $this->validator);

        $container = new Container();
        $this->controller->setContainer($container);
    }

    public function testSearchReturnsResults(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->searchService->expects(self::once())
            ->method('search')
            ->willReturn([
                'data' => [['id' => '123', 'name' => 'Keyboard']],
                'total' => 1,
            ]);

        $request = new Request(query: ['query' => 'keyboard']);
        $response = $this->controller->search($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame(1, $content['total']);
    }

    public function testSearchReturnsBadRequestOnValidationErrors(): void
    {
        $violation = new ConstraintViolation(
            'Page must be a positive number.',
            null, [], null, 'page', null,
        );

        $this->validator->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        $request = new Request(query: ['page' => '-1']);
        $response = $this->controller->search($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('errors', $content);
        self::assertSame('Page must be a positive number.', $content['errors']['page']);
    }

    public function testSearchReturnsServiceUnavailableOnException(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->searchService->method('search')
            ->willThrowException(new \RuntimeException('Elasticsearch is down'));

        $request = new Request(query: ['query' => 'test']);
        $response = $this->controller->search($request);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        self::assertSame('Search service is currently unavailable.', $content['error']);
    }

    public function testSearchWithAllParameters(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->searchService->method('search')->willReturn(['data' => [], 'total' => 0]);

        $request = new Request(query: [
            'query' => 'keyboard',
            'categoryId' => '550e8400-e29b-41d4-a716-446655440000',
            'minPrice' => '10',
            'maxPrice' => '100',
            'page' => '2',
            'limit' => '50',
        ]);

        $response = $this->controller->search($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
