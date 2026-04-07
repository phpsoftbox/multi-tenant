<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\Auth\Contracts\UserInterface;

final readonly class TestAuthUser implements UserInterface
{
    public function __construct(
        private int|string|null $id,
    ) {
    }

    public function id(): int|string|null
    {
        return $this->id;
    }
}
