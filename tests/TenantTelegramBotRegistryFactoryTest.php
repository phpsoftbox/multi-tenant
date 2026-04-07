<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use Closure;
use PhpSoftBox\MultiTenant\Telegram\TenantTelegramBotRegistryFactory;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Bot\NullUpdateHandler;
use PhpSoftBox\Telegram\Bot\TelegramBot;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use PhpSoftBox\Telegram\Bot\UpdateHandlerInterface;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

#[CoversClass(TenantTelegramBotRegistryFactory::class)]
final class TenantTelegramBotRegistryFactoryTest extends TestCase
{
    #[Test]
    public function testCreatesRegistryFromTenantTelegramNode(): void
    {
        $factory = $this->factory(['main']);
        $tenant  = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram' => [
                    'bot_name'  => '@MainBot',
                    'bot_code'  => 'main',
                    'bot_token' => 'token-main',
                ],
            ],
        );

        $registry = $factory->create($tenant);

        $this->assertSame(['main'], $registry->names());
        $this->assertSame('main', $registry->defaultName());
        $this->assertSame('token-main', $registry->token('main'));
    }

    #[Test]
    public function testDerivesBotCodeFromTelegramBotNameWhenCodeMissing(): void
    {
        $factory = $this->factory(['main']);
        $tenant  = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram' => [
                    'bot_name'  => '@Main',
                    'bot_token' => 'token-account',
                ],
            ],
        );

        $registry = $factory->create($tenant);

        $this->assertSame(['main'], $registry->names());
        $this->assertSame('main', $registry->defaultName());
        $this->assertSame('token-account', $registry->token('main'));
    }

    #[Test]
    public function testReturnsEmptyRegistryWhenBotCodeAndBotNameAreMissing(): void
    {
        $factory = $this->factory(['main_tenant']);
        $tenant  = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram' => [
                    'bot_token' => 'token-main-tenant',
                ],
            ],
        );

        $registry = $factory->create($tenant);

        $this->assertSame([], $registry->names());
    }

    #[Test]
    public function testAppliesTokenResolver(): void
    {
        $factory = $this->factory(['main'], static fn (string $token): string => $token === 'enc-token' ? 'dec-token' : $token);
        $tenant  = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram' => [
                    'bot_name'  => '@MainBot',
                    'bot_code'  => 'main',
                    'bot_token' => 'enc-token',
                ],
            ],
        );

        $registry = $factory->create($tenant);

        $this->assertSame('dec-token', $registry->token('main'));
    }

    #[Test]
    public function testSkipsTenantBotThatIsNotInCoreRegistry(): void
    {
        $factory = $this->factory(['main']);
        $tenant  = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram' => [
                    'bot_code'  => 'custom',
                    'bot_token' => 'token-custom',
                ],
            ],
        );

        $registry = $factory->create($tenant);

        $this->assertSame([], $registry->names());
    }

    #[Test]
    public function testUsesCustomHandlerResolverWhenProvided(): void
    {
        $factory = $this->factory(
            ['main_tenant'],
            null,
            static function (): UpdateHandlerInterface {
                return new class () implements UpdateHandlerInterface {
                    public function handle(Update $update): void
                    {
                    }
                };
            },
        );

        $tenant = new TenantDefinition(
            id: '1',
            name: 'Tenant 1',
            host: null,
            databaseConnection: 'tenant',
            data: [
                'telegram' => [
                    'bot_code'  => 'main_tenant',
                    'bot_token' => 'token-main-tenant',
                ],
            ],
        );

        $registry = $factory->create($tenant);
        $bot      = $registry->bot('main_tenant');

        self::assertNotNull($bot);
        self::assertNotInstanceOf(NullUpdateHandler::class, $bot->handler());
    }

    /**
     * @param list<string> $allowedBotCodes
     */
    private function factory(
        array $allowedBotCodes,
        ?callable $tokenResolver = null,
        ?callable $handlerResolver = null,
    ): TenantTelegramBotRegistryFactory {
        $bots = [];
        foreach ($allowedBotCodes as $botCode) {
            $bots[$botCode] = new TelegramBot(
                name: $botCode,
                token: 'core-' . $botCode . '-token',
                client: new TelegramClient(
                    token: 'core-' . $botCode . '-token',
                    httpClient: $this->createMock(ClientInterface::class),
                    requestFactory: $this->createMock(RequestFactoryInterface::class),
                    streamFactory: $this->createMock(StreamFactoryInterface::class),
                ),
                handler: new NullUpdateHandler(),
            );
        }

        return new TenantTelegramBotRegistryFactory(
            coreRegistry: new TelegramBotRegistry(defaultBot: 'main', bots: $bots),
            httpClient: $this->createMock(ClientInterface::class),
            requestFactory: $this->createMock(RequestFactoryInterface::class),
            streamFactory: $this->createMock(StreamFactoryInterface::class),
            tokenResolver: $tokenResolver !== null ? Closure::fromCallable($tokenResolver) : null,
            handlerResolver: $handlerResolver !== null
                ? static fn (string $code, TelegramClient $client, TenantDefinition $tenant): ?UpdateHandlerInterface => $handlerResolver()
                : null,
        );
    }
}
