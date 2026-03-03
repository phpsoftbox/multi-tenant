<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Repository\Auth;

use PhpSoftBox\MultiTenant\Entity\Auth\User;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;

final readonly class UserRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findByPhone(string $phone): ?User
    {
        $entity = $this->em
            ->queryFor(User::class)
            ->where('phone = :phone', ['phone' => $phone])
            ->limit(1)
            ->fetchEntity();

        return $entity instanceof User ? $entity : null;
    }

    public function findByEmail(string $email): ?User
    {
        $entity = $this->em
            ->queryFor(User::class)
            ->where('email = :email', ['email' => $email])
            ->limit(1)
            ->fetchEntity();

        return $entity instanceof User ? $entity : null;
    }

    public function create(
        string $name,
        string $phone,
        ?string $email,
        string $passwordHash,
        string $authToken,
        string $status = 'active',
    ): User {
        $user = new User(
            name: $name,
            phone: $phone,
            email: $email,
            status: $status,
            passwordHash: $passwordHash,
            authToken: $authToken,
        );

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
