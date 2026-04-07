<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\GuardInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TestUserGuard implements GuardInterface
{
    public function __construct(
        private ?UserInterface $user,
    ) {
    }

    public function user(ServerRequestInterface $request): ?UserInterface
    {
        return $this->user;
    }
}
