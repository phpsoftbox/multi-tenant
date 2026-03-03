<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Switcher;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\TelegramRegistrySwitcherInterface;
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Bot\NullUpdateHandler;
use PhpSoftBox\Telegram\Bot\TelegramBot;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function array_pop;
use function is_array;
use function is_string;
use function reset;
use function trim;

final class TelegramBotRegistrySwitcher implements TelegramRegistrySwitcherInterface
{
    /** @var list<array<string, TelegramBot>> */
    private array $stack = [];

    public function __construct(
        private readonly TelegramBotRegistry $registry,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function activate(array $config, TenantContext $context): void
    {
        $previous      = $this->readBots();
        $this->stack[] = $previous;

        $bots = $this->buildBots($config['bots'] ?? []);

        if ($bots === []) {
            $this->writeBots([]);

            return;
        }

        $registryDefault  = $this->registry->defaultName();
        $requestedDefault = $this->normalizeString($config['default'] ?? null);
        if ($requestedDefault !== null && isset($bots[$requestedDefault])) {
            $bots[$registryDefault] = $bots[$requestedDefault];
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
        $previous = $this->stack !== [] ? array_pop($this->stack) : null;
        if ($previous === null) {
            return;
        }

        $this->writeBots($previous);
    }

    /**
     * @return array<string, TelegramBot>
     */
    private function buildBots(mixed $botsRaw): array
    {
        if (!is_array($botsRaw)) {
            return [];
        }

        $bots = [];
        foreach ($botsRaw as $bot) {
            if (!is_array($bot)) {
                continue;
            }

            $code  = $this->normalizeString($bot['code'] ?? null);
            $token = $this->normalizeString($bot['token'] ?? null);
            if ($code === null || $token === null) {
                continue;
            }

            $client = new TelegramClient(
                token: $token,
                httpClient: $this->httpClient,
                requestFactory: $this->requestFactory,
                streamFactory: $this->streamFactory,
            );

            $bots[$code] = new TelegramBot(
                name: $code,
                token: $token,
                client: $client,
                handler: new NullUpdateHandler(),
            );
        }

        return $bots;
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

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
