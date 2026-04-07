<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\EntityChangeInitiatorResolverInterface;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function is_int;
use function is_string;
use function trim;

final class RequestAttributeEntityChangeInitiatorResolver implements EntityChangeInitiatorResolverInterface
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly ?string $userIdAttribute = 'user_id',
        private readonly ?string $userAttribute = 'user',
    ) {
    }

    public function resolve(): int|string|null
    {
        if ($this->userIdAttribute !== null) {
            $resolved = $this->normalizeScalarId($this->request->getAttribute($this->userIdAttribute));
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($this->userAttribute !== null) {
            $resolved = $this->resolveUserId($this->request->getAttribute($this->userAttribute));
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveUserId(mixed $user): int|string|null
    {
        if ($user instanceof UserInterface) {
            return $user->id();
        }

        return null;
    }

    private function normalizeScalarId(mixed $value): int|string|null
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $resolved = trim($value);
        if ($resolved === '') {
            return null;
        }

        if (ctype_digit($resolved)) {
            $numeric = (int) $resolved;

            return $numeric > 0 ? $numeric : null;
        }

        return $resolved;
    }
}
