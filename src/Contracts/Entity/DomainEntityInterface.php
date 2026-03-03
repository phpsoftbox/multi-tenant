<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts\Entity;

use PhpSoftBox\Orm\Contracts\EntityInterface;

interface DomainEntityInterface extends EntityInterface
{
    public function tenantId(): string;

    public function domain(): string;

    public function isPrimary(): bool;

    public function enabled(): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function data(): ?array;
}
