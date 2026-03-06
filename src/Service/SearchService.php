<?php

namespace App\Service;

use App\Contract\SearchServiceInterface;
use App\DTO\SearchRequest;
use App\Entity\Product;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;
use Elastica\Query\Range;
use Elastica\Query\Term;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Index\IndexManager;

final readonly class SearchService implements SearchServiceInterface
{
    public function __construct(
        private PaginatedFinderInterface $productsFinder,
        private IndexManager $indexManager,
    ) {
    }

    public function search(SearchRequest $request): array
    {
        $boolQuery = new BoolQuery();

        if ($request->query !== null && $request->query !== '') {
            $multiMatch = new MultiMatch();
            $multiMatch->setQuery($request->query);
            $multiMatch->setFields(['name^3', 'description']);
            $multiMatch->setType('best_fields');
            $multiMatch->setFuzziness('AUTO');
            $boolQuery->addMust($multiMatch);
        }

        if ($request->categoryId !== null) {
            $boolQuery->addFilter(new Term(['categoryId' => $request->categoryId]));
        }

        if ($request->minPrice !== null || $request->maxPrice !== null) {
            $rangeParams = [];
            if ($request->minPrice !== null) {
                $rangeParams['gte'] = $request->minPrice;
            }
            if ($request->maxPrice !== null) {
                $rangeParams['lte'] = $request->maxPrice;
            }
            $boolQuery->addFilter(new Range('price', $rangeParams));
        }

        $boolQuery->addFilter(new Term(['active' => true]));

        $query = new Query($boolQuery);
        $query->setFrom(($request->page - 1) * $request->limit);
        $query->setSize($request->limit);
        $query->addSort(['_score' => 'desc']);
        $query->addSort(['createdAt' => 'desc']);

        $paginator = $this->productsFinder->findPaginated($boolQuery);
        $paginator->setMaxPerPage($request->limit);
        $paginator->setCurrentPage($request->page);

        $results = [];
        foreach ($paginator->getCurrentPageResults() as $result) {
            $results[] = $result;
        }

        return [
            'data' => $results,
            'total' => $paginator->getNbResults(),
            'page' => $request->page,
            'limit' => $request->limit,
        ];
    }

    public function indexProduct(Product $product): void
    {
        $index = $this->indexManager->getIndex('products');

        $document = new \Elastica\Document(
            (string) $product->getId(),
            [
                'id' => (string) $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => (float) $product->getPrice(),
                'sku' => $product->getSku(),
                'stock' => $product->getStock(),
                'categoryId' => $product->getCategoryId() ? (string) $product->getCategoryId() : null,
                'active' => $product->isActive(),
                'createdAt' => $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $product->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ],
        );

        $index->addDocument($document);
        $index->refresh();
    }

    public function removeProduct(string $id): void
    {
        $index = $this->indexManager->getIndex('products');

        try {
            $index->deleteById($id);
            $index->refresh();
        } catch (\Elastica\Exception\NotFoundException) {
        }
    }
}
