<?php

namespace App\Tests\Unit\Repository;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class ProductRepositoryTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(Product::class);

        $registry->method('getManagerForClass')->willReturn($em);
        $em->method('getClassMetadata')->willReturn($metadata);

        $repository = new ProductRepository($registry);

        self::assertInstanceOf(ProductRepository::class, $repository);
    }

    public function testExtendsServiceEntityRepository(): void
    {
        $reflection = new \ReflectionClass(ProductRepository::class);

        self::assertSame(
            'Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository',
            $reflection->getParentClass()->getName(),
        );
    }
}
