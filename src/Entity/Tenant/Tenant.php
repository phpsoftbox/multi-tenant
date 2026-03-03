<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Entity\Tenant;

use PhpSoftBox\MultiTenant\Contracts\Entity\TenantEntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'tenants', connection: 'default')]
class Tenant implements TenantEntityInterface
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'auto')]
        #[Column(type: 'int')]
        public ?int $id = null,
        #[Column(name: 'user_id', type: 'int', nullable: true)]
        public ?int $userId = null,
        #[Column(name: 'tenant_id', type: 'string', length: 100)]
        public string $tenantId = '',
        #[Column(type: 'string', length: 255)]
        public string $name = '',
        #[Column(name: 'database_connection', type: 'string', length: 100)]
        public string $databaseConnection = 'default',
        #[Column(name: 'database_name', type: 'string', length: 255, nullable: true)]
        public ?string $databaseName = null,
        #[Column(type: 'bool')]
        public bool $enabled = true,
        #[Column(type: 'json', nullable: true)]
        public ?array $data = null,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function tenantName(): string
    {
        return $this->name;
    }

    public function databaseConnection(): string
    {
        return $this->databaseConnection;
    }

    public function databaseName(): ?string
    {
        return $this->databaseName;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function data(): ?array
    {
        return $this->data;
    }
}
