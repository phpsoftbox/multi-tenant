<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Repository\Auth;

use PhpSoftBox\MultiTenant\Entity\Auth\Role;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

final readonly class RoleRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findByName(string $name): ?Role
    {
        $entity = $this->em
            ->queryFor(Role::class)
            ->where('name = :name', ['name' => $name])
            ->limit(1)
            ->fetchEntity();

        return $entity instanceof Role ? $entity : null;
    }

    public function findById(int $id): ?Role
    {
        $entity = $this->em
            ->queryFor(Role::class)
            ->where('id = :id', ['id' => $id])
            ->limit(1)
            ->fetchEntity();

        return $entity instanceof Role ? $entity : null;
    }
}
