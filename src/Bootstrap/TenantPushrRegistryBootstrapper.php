<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\PushrRegistrySwitcherInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;

use function is_array;
use function is_string;
use function trim;

final class TenantPushrRegistryBootstrapper implements TenantBootstrapperInterface
{
    public function __construct(
        private readonly PushrRegistrySwitcherInterface $switcher,
        private readonly string $extensionKey = 'pushr.credentials',
        private readonly int $bootstrapPriority = 850,
    ) {
    }

    public function supports(TenantRuntimeScope $scope): bool
    {
        return true;
    }

    public function priority(): int
    {
        return $this->bootstrapPriority;
    }

    public function bootstrap(TenantContext $context): void
    {
        $this->switcher->activate(
            $this->normalize($context->get($this->extensionKey, [])),
            $context,
        );
    }

    public function teardown(TenantContext $context): void
    {
        $this->switcher->deactivate($context);
    }

    /**
     * @return array{app_id:?string, secret:?string}
     */
    private function normalize(mixed $extension): array
    {
        if (!is_array($extension)) {
            return ['app_id' => null, 'secret' => null];
        }

        $appId = $extension['app_id'] ?? null;
        if (!is_string($appId) || trim($appId) === '') {
            $appId = null;
        } else {
            $appId = trim($appId);
        }

        $secret = $extension['secret'] ?? null;
        if (!is_string($secret) || trim($secret) === '') {
            $secret = null;
        } else {
            $secret = trim($secret);
        }

        return [
            'app_id' => $appId,
            'secret' => $secret,
        ];
    }
}
