<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Entity\Auth;

use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'users', connection: 'default')]
final class User implements EntityInterface
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'auto')]
        #[Column(type: 'int')]
        public ?int $id = null,
        #[Column(type: 'string', length: 150)]
        public string $name = '',
        #[Column(type: 'string', length: 32)]
        public string $phone = '',
        #[Column(type: 'string', length: 190, nullable: true)]
        public ?string $email = null,
        #[Column(type: 'string', length: 32)]
        public string $status = 'active',
        #[Column(name: 'password_hash', type: 'string', length: 255)]
        public string $passwordHash = '',
        #[Column(name: 'auth_token', type: 'string', length: 64)]
        public string $authToken = '',
        #[Column(name: 'created_datetime', type: 'date_point')]
        public DatePoint $createdDatetime = new DatePoint(),
        #[Column(name: 'updated_datetime', type: 'date_point')]
        public DatePoint $updatedDatetime = new DatePoint(),
        #[Column(name: 'deleted_datetime', type: 'date_point', nullable: true)]
        public ?DatePoint $deletedDatetime = null,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
