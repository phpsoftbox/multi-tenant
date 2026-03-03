<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts\Entity;

use PhpSoftBox\Orm\Contracts\EntityInterface;

interface TenantEntityInterface extends EntityInterface
{
    public function tenantId(): int;

    public function tenantName(): string;

    public function databaseConnection(): string;

    public function databaseName(): ?string;

    public function enabled(): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function data(): ?array;
}
