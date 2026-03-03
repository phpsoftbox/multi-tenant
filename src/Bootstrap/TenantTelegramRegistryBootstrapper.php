<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Bootstrap;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TelegramRegistrySwitcherInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantBootstrapperInterface;

use function is_array;
use function is_string;
use function trim;

final class TenantTelegramRegistryBootstrapper implements TenantBootstrapperInterface
{
    public function __construct(
        private readonly TelegramRegistrySwitcherInterface $switcher,
        private readonly string $extensionKey = 'telegram.bots',
        private readonly int $bootstrapPriority = 900,
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
        $extension = $context->get($this->extensionKey, []);
        $this->switcher->activate($this->normalize($extension), $context);
    }

    public function teardown(TenantContext $context): void
    {
        $this->switcher->deactivate($context);
    }

    /**
     * @return array{bots:list<array<string, mixed>>, default:?string}
     */
    private function normalize(mixed $extension): array
    {
        if (!is_array($extension)) {
            return ['bots' => [], 'default' => null];
        }

        $botsRaw = $extension['bots'] ?? [];
        $bots    = [];
        if (is_array($botsRaw)) {
            foreach ($botsRaw as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $code  = $item['code'] ?? null;
                $token = $item['token'] ?? null;

                if (!is_string($code) || trim($code) === '') {
                    continue;
                }

                if (!is_string($token) || trim($token) === '') {
                    continue;
                }

                $bots[] = $item;
            }
        }

        $default = $extension['default'] ?? null;
        if (!is_string($default) || trim($default) === '') {
            $default = null;
        } else {
            $default = trim($default);
        }

        return [
            'bots'    => $bots,
            'default' => $default,
        ];
    }
}
