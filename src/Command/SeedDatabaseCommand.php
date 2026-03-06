<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Service\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-database',
    description: 'Seed categories and products with real data',
)]
final class SeedDatabaseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SearchService $searchService,
        private readonly IndexManager $indexManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Seeding Database');

        $this->truncate($io);
        $categoryMap = $this->seedCategories($io);
        $this->seedProducts($io, $categoryMap);

        $io->success('Database seeded successfully!');

        return Command::SUCCESS;
    }

    private function truncate(SymfonyStyle $io): void
    {
        $io->section('Truncating tables and Elasticsearch indices');

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE products CASCADE');
        $connection->executeStatement('TRUNCATE TABLE categories CASCADE');

        try {
            $index = $this->indexManager->getIndex('products');
            $index->deleteByQuery(new \Elastica\Query\MatchAll());
            $index->refresh();
            $io->writeln('  Elasticsearch products index cleared');
        } catch (\Throwable $e) {
            $io->warning('Could not clear Elasticsearch: ' . $e->getMessage());
        }

        $io->writeln('  Tables truncated');
    }

    private function seedCategories(SymfonyStyle $io): array
    {
        $io->section('Seeding categories');

        $categoriesData = require __DIR__ . '/../DataFixtures/Data/CategoryData.php';
        $categoryMap = [];

        foreach ($categoriesData as $data) {
            $category = new Category();
            $category->setName($data['name']);
            $category->setSlug($data['slug']);
            $category->setDescription($data['description']);

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $categoryMap[$data['name']] = $category->getId();

            $this->indexCategory($category);
        }

        $io->writeln(sprintf('  %d categories seeded and indexed in Elasticsearch', count($categoriesData)));

        return $categoryMap;
    }

    private function seedProducts(SymfonyStyle $io, array $categoryMap): void
    {
        $io->section('Seeding products');

        $eventManager = $this->entityManager->getEventManager();
        $listenersToRemove = [];

        foreach ($eventManager->getAllListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof \App\EventListener\ProductIndexListener) {
                    $listenersToRemove[$event] = $listener;
                    $eventManager->removeEventListener([$event], $listener);
                }
            }
        }

        $productsData = require __DIR__ . '/../DataFixtures/Data/ProductData.php';
        $count = 0;

        foreach ($productsData as $data) {
            $product = new Product();
            $product->setName($data['name']);
            $product->setDescription($data['description']);
            $product->setPrice(number_format($data['price'], 2, '.', ''));
            $product->setSku($data['sku']);
            $product->setStock($data['stock']);

            if (isset($data['category'], $categoryMap[$data['category']])) {
                $product->setCategoryId($categoryMap[$data['category']]);
            }

            $this->entityManager->persist($product);
            $count++;

            if ($count % 20 === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
        $io->writeln(sprintf('  %d products seeded in database', $count));

        $io->writeln('  Indexing products in Elasticsearch...');
        $products = $this->entityManager->getRepository(Product::class)->findAll();
        foreach ($products as $product) {
            $this->searchService->indexProduct($product);
        }

        $io->writeln(sprintf('  %d products indexed in Elasticsearch', count($products)));

        foreach ($listenersToRemove as $event => $listener) {
            $eventManager->addEventListener([$event], $listener);
        }
    }

    private function indexCategory(Category $category): void
    {
        try {
            $index = $this->indexManager->getIndex('products');

            $document = new \Elastica\Document(
                'category_' . (string) $category->getId(),
                [
                    'id' => (string) $category->getId(),
                    'name' => $category->getName(),
                    'description' => $category->getDescription(),
                    'type' => 'category',
                ],
            );

            $index->addDocument($document);
            $index->refresh();
        } catch (\Throwable) {
        }
    }
}
