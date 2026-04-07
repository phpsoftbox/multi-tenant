<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\Orm\Contracts\EntityInterface;

final class TenantRuntimeEntity implements EntityInterface
{
    public function __construct(
        public ?int $id,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
