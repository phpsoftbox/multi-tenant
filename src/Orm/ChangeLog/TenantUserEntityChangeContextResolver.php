<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm\ChangeLog;

use PhpSoftBox\Auth\Contracts\UserInterface as AuthUserInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\MultiTenant\Context\TenantContextResolver;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContext;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContextResolverInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function ctype_digit;
use function is_array;
use function is_int;
use function is_string;
use function strtoupper;
use function trim;

final class TenantUserEntityChangeContextResolver implements EntityChangeContextResolverInterface
{
    /**
     * @var list<string|null>
     */
    private readonly array $guards;

    /**
     * @param list<string|null> $guards
     */
    public function __construct(
        private readonly TenantContextResolver $tenantResolver,
        private readonly ?ServerRequestInterface $request = null,
        private readonly ?AuthManager $auth = null,
        array $guards = [null, 'tenant'],
    ) {
        $this->guards = $guards;
    }

    public function resolve(): EntityChangeContext
    {
        $initiatorId = $this->resolveInitiatorId();
        $metadata    = [];

        $tenant = $this->tenantResolver->get();
        if ($tenant !== null) {
            $tenantId = trim($tenant->id);
            if ($tenantId !== '') {
                $metadata['tenant_id'] = $tenantId;
            }
        }

        if ($this->request instanceof ServerRequestInterface) {
            $method = trim(strtoupper($this->request->getMethod()));
            if ($method !== '') {
                $metadata['request_method'] = $method;
            }

            $path = trim($this->request->getUri()->getPath());
            if ($path !== '') {
                $metadata['request_path'] = $path;
            }
        }

        return new EntityChangeContext(
            initiatorId: $initiatorId,
            initiatorType: $initiatorId !== null ? 'user' : 'system',
            metadata: $metadata,
        );
    }

    private function resolveInitiatorId(): int|string|null
    {
        if (!$this->request instanceof ServerRequestInterface) {
            return null;
        }

        foreach (['user_id', 'auth_user_id'] as $attribute) {
            $requestUserId = $this->normalizeInitiatorId($this->request->getAttribute($attribute));
            if ($requestUserId !== null) {
                return $requestUserId;
            }
        }

        foreach (['_authUser', 'authUser', 'user'] as $attribute) {
            $resolved = $this->resolveUserId($this->request->getAttribute($attribute));
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (!$this->auth instanceof AuthManager) {
            return null;
        }

        foreach ($this->guards as $guard) {
            try {
                $user = $this->auth->guard($guard)->user($this->request);
            } catch (Throwable) {
                continue;
            }

            $resolved = $this->resolveUserId($user);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveUserId(mixed $user): int|string|null
    {
        if ($user instanceof AuthUserInterface) {
            $id = $this->normalizeInitiatorId($user->id());
            if ($id !== null) {
                return $id;
            }

            return $this->normalizeInitiatorId($user->get('id'));
        }

        if (is_array($user)) {
            return $this->normalizeInitiatorId($user['id'] ?? null);
        }

        return null;
    }

    private function normalizeInitiatorId(mixed $value): int|string|null
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
