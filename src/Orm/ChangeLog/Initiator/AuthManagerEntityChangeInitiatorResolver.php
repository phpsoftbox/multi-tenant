<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator;

use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\EntityChangeInitiatorResolverInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class AuthManagerEntityChangeInitiatorResolver implements EntityChangeInitiatorResolverInterface
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly AuthManager $auth,
        private readonly ?string $guard = null,
    ) {
    }

    public function resolve(): int|string|null
    {
        try {
            return $this->auth->guard($this->guard)->user($this->request)?->id();
        } catch (Throwable) {
            return null;
        }
    }
}
