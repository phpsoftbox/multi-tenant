<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Switcher\TelegramBotRegistrySwitcher;
use PhpSoftBox\MultiTenant\Telegram\TenantTelegramBotRegistryFactory;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Bot\NullUpdateHandler;
use PhpSoftBox\Telegram\Bot\TelegramBot;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

#[CoversClass(TelegramBotRegistrySwitcher::class)]
final class TelegramBotRegistrySwitcherTest extends TestCase
{
    #[Test]
    public function testActivateReplacesBotsFromTenantRegistryAndDeactivateRestoresCore(): void
    {
        $registry = $this->coreRegistry();
        $factory  = $this->tenantFactory(['main_tenant']);
        $switcher = new TelegramBotRegistrySwitcher($registry, $factory);

        $context = $this->context([
            'telegram' => [
                'bot_code'  => 'main_tenant',
                'bot_token' => 'tenant-token',
            ],
        ]);

        $switcher->activate([], $context);

        $this->assertSame('tenant-token', $registry->token('main_tenant'));
        $this->assertSame('tenant-token', $registry->token('account'));
        $this->assertSame('tenant-token', $registry->token());

        $switcher->deactivate($context);

        $this->assertSame('core-account-token', $registry->token('account'));
        $this->assertSame('core-account-token', $registry->token());
    }

    #[Test]
    public function testActivateClearsRegistryWhenTenantHasNoAllowedBots(): void
    {
        $registry = $this->coreRegistry();
        $factory  = $this->tenantFactory(['main_tenant']);
        $switcher = new TelegramBotRegistrySwitcher($registry, $factory);

        $context = $this->context([]);

        $switcher->activate([], $context);

        $this->assertSame([], $registry->names());
        $this->assertNull($registry->token('account'));

        $switcher->deactivate($context);

        $this->assertSame('core-account-token', $registry->token('account'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function context(array $data): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-alpha', 'Tenant Alpha', null, 'tenant', data: $data));
    }

    private function coreRegistry(): TelegramBotRegistry
    {
        $httpClient     = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory  = $this->createMock(StreamFactoryInterface::class);

        return new TelegramBotRegistry('account', [
            new TelegramBot(
                'account',
                'core-account-token',
                new TelegramClient('core-account-token', $httpClient, $requestFactory, $streamFactory),
                new NullUpdateHandler(),
            ),
        ]);
    }

    /**
     * @param list<string> $allowedCodes
     */
    private function tenantFactory(array $allowedCodes): TenantTelegramBotRegistryFactory
    {
        $httpClient     = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory  = $this->createMock(StreamFactoryInterface::class);

        $bots = [];
        foreach ($allowedCodes as $code) {
            $token  = 'core-' . $code . '-token';
            $bots[] = new TelegramBot(
                name: $code,
                token: $token,
                client: new TelegramClient($token, $httpClient, $requestFactory, $streamFactory),
                handler: new NullUpdateHandler(),
            );
        }

        return new TenantTelegramBotRegistryFactory(
            coreRegistry: new TelegramBotRegistry(defaultBot: 'main', bots: $bots),
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );
    }
}
