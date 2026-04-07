<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Switcher;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\TelegramRegistrySwitcherInterface;
use PhpSoftBox\MultiTenant\Telegram\TenantTelegramBotRegistryFactory;
use PhpSoftBox\Telegram\Bot\TelegramBot;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;

use function array_pop;
use function reset;

final class TelegramBotRegistrySwitcher implements TelegramRegistrySwitcherInterface
{
    /** @var list<array<string, TelegramBot>> */
    private array $stack = [];

    public function __construct(
        private readonly TelegramBotRegistry $registry,
        private readonly TenantTelegramBotRegistryFactory $tenantRegistryFactory,
    ) {
    }

    public function activate(array $config, TenantContext $context): void
    {
        unset($config);

        $previous      = $this->readBots();
        $this->stack[] = $previous;

        $tenantRegistry = $this->tenantRegistryFactory->create($context->tenant());
        $bots           = $tenantRegistry->all();

        if ($bots === []) {
            $this->writeBots([]);

            return;
        }

        $registryDefault = $this->registry->defaultName();
        $tenantDefault   = $tenantRegistry->defaultName();

        if ($tenantDefault !== '' && isset($bots[$tenantDefault])) {
            $bots[$registryDefault] = $bots[$tenantDefault];
        } elseif (!isset($bots[$registryDefault])) {
            $first = reset($bots);
            if ($first instanceof TelegramBot) {
                $bots[$registryDefault] = $first;
            }
        }

        $this->writeBots($bots);
    }

    public function deactivate(TenantContext $context): void
    {
        unset($context);

        $previous = $this->stack !== [] ? array_pop($this->stack) : null;
        if ($previous === null) {
            return;
        }

        $this->writeBots($previous);
    }

    /**
     * @return array<string, TelegramBot>
     */
    private function readBots(): array
    {
        return $this->registry->all();
    }

    /**
     * @param array<string, TelegramBot> $bots
     */
    private function writeBots(array $bots): void
    {
        $this->registry->replaceBots($bots);
    }
}
