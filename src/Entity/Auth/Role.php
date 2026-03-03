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

#[Entity(table: 'roles', connection: 'default')]
final class Role implements EntityInterface
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'auto')]
        #[Column(type: 'int')]
        public ?int $id,
        #[Column(type: 'string', length: 100)]
        public string $name,
        #[Column(type: 'string', length: 150, nullable: true)]
        public ?string $label,
        #[Column(name: 'admin_access', type: 'bool')]
        public bool $adminAccess,
        #[Column(name: 'created_datetime', type: 'date_point')]
        public DatePoint $createdDatetime,
        #[Column(name: 'updated_datetime', type: 'date_point')]
        public DatePoint $updatedDatetime,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
