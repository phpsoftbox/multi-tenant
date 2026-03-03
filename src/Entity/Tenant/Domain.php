<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Entity\Tenant;

use PhpSoftBox\MultiTenant\Contracts\Entity\DomainEntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'domains', connection: 'default')]
class Domain implements DomainEntityInterface
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'auto')]
        #[Column(type: 'int')]
        public ?int $id = null,
        #[Column(name: 'tenant_id', type: 'string', length: 100)]
        public string $tenantId = '',
        #[Column(type: 'string', length: 255)]
        public string $domain = '',
        #[Column(name: 'is_primary', type: 'bool')]
        public bool $isPrimary = false,
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

    public function domain(): string
    {
        return $this->domain;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
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
