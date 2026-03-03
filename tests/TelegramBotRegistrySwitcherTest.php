<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Switcher\TelegramBotRegistrySwitcher;
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
    public function testActivateReplacesTokensAndDeactivateRestoresBots(): void
    {
        $httpClient     = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory  = $this->createMock(StreamFactoryInterface::class);

        $initialClient = new TelegramClient(
            token: 'token-old',
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );

        $registry = new TelegramBotRegistry('account', [
            new TelegramBot('account', 'token-old', $initialClient, new NullUpdateHandler()),
        ]);

        $switcher = new TelegramBotRegistrySwitcher(
            registry: $registry,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );

        $context = $this->context();

        $switcher->activate([
            'bots' => [
                ['code' => 'account', 'token' => 'token-new'],
            ],
            'default' => 'account',
        ], $context);

        $this->assertSame('token-new', $registry->token('account'));
        $this->assertSame('token-new', $registry->token());

        $switcher->deactivate($context);

        $this->assertSame('token-old', $registry->token('account'));
        $this->assertSame('token-old', $registry->token());
    }

    #[Test]
    public function testActivateMapsRegistryDefaultToRequestedDefaultBot(): void
    {
        $httpClient     = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory  = $this->createMock(StreamFactoryInterface::class);

        $initialClient = new TelegramClient(
            token: 'token-old',
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );

        $registry = new TelegramBotRegistry('account', [
            new TelegramBot('account', 'token-old', $initialClient, new NullUpdateHandler()),
        ]);

        $switcher = new TelegramBotRegistrySwitcher(
            registry: $registry,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );

        $context = $this->context();

        $switcher->activate([
            'bots' => [
                ['code' => 'main', 'token' => 'token-main'],
            ],
            'default' => 'main',
        ], $context);

        $this->assertSame('token-main', $registry->token('main'));
        $this->assertSame('token-main', $registry->token());
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-alpha', 'Tenant Alpha', null, 'tenant'));
    }
}
