<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\Contracts\BulkEntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\RepositoryInterface;
use PhpSoftBox\Orm\Contracts\UnitOfWorkInterface;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Relation\PivotRelationManager;
use Ramsey\Uuid\UuidInterface;

final readonly class TenantEntityManager implements TenantEntityManagerInterface
{
    public function __construct(
        private TenantEntityManagerRegistryInterface $entityManagers,
    ) {
    }

    public function connection(): ConnectionInterface
    {
        return $this->em()->connection();
    }

    public function unitOfWork(): UnitOfWorkInterface
    {
        return $this->em()->unitOfWork();
    }

    public function persist(EntityInterface $entity): void
    {
        $this->em()->persist($entity);
    }

    public function remove(EntityInterface $entity): void
    {
        $this->em()->remove($entity);
    }

    public function flush(): void
    {
        $this->em()->flush();
    }

    public function repository(string $entityClass): RepositoryInterface
    {
        return $this->em()->repository($entityClass);
    }

    public function bulkRepository(string $entityClass): BulkEntityRepositoryInterface
    {
        return $this->em()->bulkRepository($entityClass);
    }

    public function find(string $entityClass, int|string|UuidInterface $id): ?EntityInterface
    {
        return $this->em()->find($entityClass, $id);
    }

    public function queryFor(string $entityClass, bool $withDeleted = false): OrmSelectQueryBuilder
    {
        return $this->em()->queryFor($entityClass, $withDeleted);
    }

    public function forceRemove(EntityInterface $entity): void
    {
        $this->em()->forceRemove($entity);
    }

    public function load(EntityInterface|iterable $entities, string|array $relations): void
    {
        $this->em()->load($entities, $relations);
    }

    public function metadataProvider(): MetadataProviderInterface
    {
        return $this->em()->metadataProvider();
    }

    public function refresh(EntityInterface $entity): void
    {
        $this->em()->refresh($entity);
    }

    public function pivot(EntityInterface $owner, string $relationProperty): PivotRelationManager
    {
        return $this->em()->pivot($owner, $relationProperty);
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManagers->tenant();
    }
}
