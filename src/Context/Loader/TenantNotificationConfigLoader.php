<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Context\Loader;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantExtensionLoaderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

use function array_key_exists;
use function is_array;

final readonly class TenantNotificationConfigLoader implements TenantExtensionLoaderInterface
{
    public function __construct(
        private string $extensionKey = 'notifications.config',
        private int $extensionPriority = 620,
    ) {
    }

    public function key(): string
    {
        return $this->extensionKey;
    }

    public function supports(TenantRuntimeScope $scope): bool
    {
        return true;
    }

    public function priority(): int
    {
        return $this->extensionPriority;
    }

    public function load(TenantDefinition $tenant, TenantContext $context): mixed
    {
        $data = $tenant->data;
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
