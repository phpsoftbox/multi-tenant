<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\NotificationConfigSwitcherInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;

use function array_key_exists;
use function is_array;

final class TenantNotificationConfigBootstrapper implements TenantBootstrapperInterface
{
    public function __construct(
        private readonly NotificationConfigSwitcherInterface $switcher,
        private readonly string $extensionKey = 'notifications.config',
        private readonly int $bootstrapPriority = 780,
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
        $this->switcher->activate($this->resolveOverrides($context), $context);
    }

    public function teardown(TenantContext $context): void
    {
        $this->switcher->deactivate($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOverrides(TenantContext $context): array
    {
        $extension = $context->get($this->extensionKey);
        if (is_array($extension)) {
            return $extension;
        }

        $data = $context->tenant()->data;
        if (!is_array($data)) {
            return [];
        }

        if (array_key_exists('notifications_config', $data) && is_array($data['notifications_config'])) {
            return $data['notifications_config'];
        }

        $notifications = $data['notifications'] ?? null;
        if (is_array($notifications) && array_key_exists('config', $notifications) && is_array($notifications['config'])) {
            return $notifications['config'];
        }

        return [];
    }
}
