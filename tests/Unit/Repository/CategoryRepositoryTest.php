<?php

namespace App\Tests\Unit\Repository;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class CategoryRepositoryTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(Category::class);

        $registry->method('getManagerForClass')->willReturn($em);
        $em->method('getClassMetadata')->willReturn($metadata);

        $repository = new CategoryRepository($registry);

        self::assertInstanceOf(CategoryRepository::class, $repository);
    }

    public function testFindBySlugMethodExists(): void
    {
        $reflection = new \ReflectionMethod(CategoryRepository::class, 'findBySlug');

        self::assertSame('findBySlug', $reflection->getName());
        self::assertCount(1, $reflection->getParameters());
        self::assertSame('slug', $reflection->getParameters()[0]->getName());
        self::assertSame('string', $reflection->getParameters()[0]->getType()->getName());
    }

    public function testFindBySlugReturnType(): void
    {
        $reflection = new \ReflectionMethod(CategoryRepository::class, 'findBySlug');
        $returnType = $reflection->getReturnType();

        self::assertTrue($returnType->allowsNull());
        self::assertSame(Category::class, $returnType->getName());
    }
}
