<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\CliApp\Command\InMemoryCommandRegistry;
use PhpSoftBox\MultiTenant\Cli\Telegram\TelegramPollScopeHandler;
use PhpSoftBox\MultiTenant\Cli\Telegram\TelegramSyncScopeHandler;
use PhpSoftBox\MultiTenant\Cli\Telegram\TelegramWebhookScopeHandler;
use PhpSoftBox\MultiTenant\Cli\TenantAuthSyncHandler;
use PhpSoftBox\MultiTenant\Cli\TenantCommandProvider;
use PhpSoftBox\MultiTenant\Cli\TenantConfigCheckHandler;
use PhpSoftBox\MultiTenant\Cli\TenantDbMigrateHandler;
use PhpSoftBox\MultiTenant\Cli\TenantDbProvisionHandler;
use PhpSoftBox\MultiTenant\Cli\TenantDbRollbackHandler;
use PhpSoftBox\MultiTenant\Cli\TenantListHandler;
use PhpSoftBox\MultiTenant\Cli\TenantProvisionDispatchHandler;
use PhpSoftBox\MultiTenant\Cli\TenantProvisionRunHandler;
use PhpSoftBox\MultiTenant\Cli\TenantPushrServeHandler;
use PhpSoftBox\MultiTenant\Cli\TenantQueueCoreRunHandler;
use PhpSoftBox\MultiTenant\Cli\TenantQueueTenantRunHandler;
use PhpSoftBox\MultiTenant\Cli\UserCreateHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(TenantCommandProvider::class)]
#[CoversMethod(TenantCommandProvider::class, 'register')]
final class TenantCommandProviderTest extends TestCase
{
    /**
     * Проверяет регистрацию всех tenant:* CLI-команд.
     */
    #[Test]
    public function testRegistersExpectedCommands(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);
        $provider = new TenantCommandProvider();

        $provider->register($registry);

        $this->assertSame(TenantListHandler::class, $registry->get('tenant:list')?->handler);
        $this->assertSame(TenantConfigCheckHandler::class, $registry->get('tenant:config:check')?->handler);
        $this->assertSame(TenantDbMigrateHandler::class, $registry->get('tenant:db:migrate')?->handler);
        $this->assertSame(TenantDbRollbackHandler::class, $registry->get('tenant:db:rollback')?->handler);
        $this->assertSame(TenantDbProvisionHandler::class, $registry->get('tenant:db:provision')?->handler);
        $this->assertSame(TenantProvisionDispatchHandler::class, $registry->get('tenant:provision:dispatch')?->handler);
        $this->assertSame(TenantProvisionRunHandler::class, $registry->get('tenant:provision:run')?->handler);
        $this->assertSame(TenantPushrServeHandler::class, $registry->get('tenant:pushr:serve')?->handler);
        $this->assertSame(TenantQueueCoreRunHandler::class, $registry->get('tenant:queue:core:run')?->handler);
        $this->assertSame(TenantQueueTenantRunHandler::class, $registry->get('tenant:queue:tenant:run')?->handler);
        $this->assertSame(TenantAuthSyncHandler::class, $registry->get('tenant:auth:sync')?->handler);
        $this->assertSame(UserCreateHandler::class, $registry->get('tenant:user:create')?->handler);
        $this->assertSame(UserCreateHandler::class, $registry->get('dispatcher:user:create')?->handler);
        $this->assertSame(UserCreateHandler::class, $registry->get('dispatcher:admin:create')?->handler);
        $this->assertSame(TelegramPollScopeHandler::class, $registry->get('telegram:poll')?->handler);
        $this->assertSame(TelegramWebhookScopeHandler::class, $registry->get('telegram:webhook')?->handler);
        $this->assertSame(TelegramSyncScopeHandler::class, $registry->get('telegram:sync')?->handler);
    }

    /**
     * Проверяет сигнатуру команды tenant:db:provision.
     */
    #[Test]
    public function testProvisionCommandHasExpectedOptions(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);

        new TenantCommandProvider()->register($registry);

        $command = $registry->get('tenant:db:provision');

        $this->assertNotNull($command);

        $options = $command->signature->options();

        $this->assertArrayHasKey('tenant', $options);
        $this->assertArrayHasKey('template', $options);
        $this->assertArrayHasKey('migrations-table', $options);
        $this->assertArrayHasKey('drop-existing', $options);
        $this->assertArrayHasKey('fail-fast', $options);

        $this->assertSame('all', $options['tenant']->default);
        $this->assertSame('migrations', $options['migrations-table']->default);
        $this->assertTrue($options['drop-existing']->flag);
        $this->assertTrue($options['fail-fast']->flag);
    }

    /**
     * Проверяет сигнатуры tenant:provision:* команд.
     */
    #[Test]
    public function testTenantProvisionCommandsHaveExpectedOptions(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);

        new TenantCommandProvider()->register($registry);

        $dispatch = $registry->get('tenant:provision:dispatch');
        $run      = $registry->get('tenant:provision:run');

        $this->assertNotNull($dispatch);
        $this->assertNotNull($run);

        $dispatchOptions = $dispatch->signature->options();
        $runOptions      = $run->signature->options();

        $this->assertArrayHasKey('tenant', $dispatchOptions);
        $this->assertArrayHasKey('template', $dispatchOptions);
        $this->assertArrayHasKey('owner-phone', $dispatchOptions);
        $this->assertArrayHasKey('owner-name', $dispatchOptions);
        $this->assertArrayHasKey('owner-email', $dispatchOptions);
        $this->assertArrayHasKey('no-confirm-phone', $dispatchOptions);
        $this->assertArrayHasKey('priority', $dispatchOptions);
        $this->assertSame('all', $dispatchOptions['tenant']->default);
        $this->assertSame(0, $dispatchOptions['priority']->default);

        $this->assertArrayHasKey('tenant', $runOptions);
        $this->assertArrayHasKey('template', $runOptions);
        $this->assertArrayHasKey('owner-phone', $runOptions);
        $this->assertArrayHasKey('owner-name', $runOptions);
        $this->assertArrayHasKey('owner-email', $runOptions);
        $this->assertArrayHasKey('no-confirm-phone', $runOptions);
        $this->assertSame('all', $runOptions['tenant']->default);
    }

    /**
     * Проверяет сигнатуру команды tenant:config:check.
     */
    #[Test]
    public function testConfigCheckCommandHasExpectedOptions(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);

        new TenantCommandProvider()->register($registry);

        $command = $registry->get('tenant:config:check');

        $this->assertNotNull($command);

        $options = $command->signature->options();

        $this->assertArrayHasKey('primary', $options);
        $this->assertArrayHasKey('connection', $options);
        $this->assertNull($options['primary']->default);
        $this->assertNull($options['connection']->default);
    }

    /**
     * Проверяет сигнатуры tenant queue команд.
     */
    #[Test]
    public function testQueueCommandsHaveExpectedOptions(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);

        new TenantCommandProvider()->register($registry);

        $core   = $registry->get('tenant:queue:core:run');
        $tenant = $registry->get('tenant:queue:tenant:run');

        $this->assertNotNull($core);
        $this->assertNotNull($tenant);

        $coreOptions   = $core->signature->options();
        $tenantOptions = $tenant->signature->options();

        $this->assertArrayHasKey('max-jobs', $coreOptions);
        $this->assertSame(0, $coreOptions['max-jobs']->default);

        $this->assertArrayHasKey('tenant', $tenantOptions);
        $this->assertArrayHasKey('max-jobs', $tenantOptions);
        $this->assertSame('all', $tenantOptions['tenant']->default);
        $this->assertSame(0, $tenantOptions['max-jobs']->default);
    }

    /**
     * Проверяет сигнатуру команды tenant:user:create.
     */
    #[Test]
    public function testUserCreateCommandHasExpectedSignature(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);

        new TenantCommandProvider()->register($registry);

        $command = $registry->get('tenant:user:create');
        $this->assertNotNull($command);

        $arguments = $command->signature->arguments();
        $options   = $command->signature->options();

        $argumentNames = array_map(static fn ($argument): string => $argument->name, $arguments);

        $this->assertContains('phone', $argumentNames);
        $this->assertContains('password', $argumentNames);
        $this->assertArrayHasKey('scope', $options);
        $this->assertArrayHasKey('tenant', $options);
        $this->assertArrayHasKey('name', $options);
        $this->assertArrayHasKey('email', $options);
        $this->assertArrayHasKey('role', $options);
        $this->assertSame('core', $options['scope']->default);
        $this->assertSame('all', $options['tenant']->default);
    }

    /**
     * Проверяет базовую сигнатуру telegram override команд.
     */
    #[Test]
    public function testTelegramOverrideCommandsHaveScopeOptions(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);

        new TenantCommandProvider()->register($registry);

        $sync    = $registry->get('telegram:sync');
        $poll    = $registry->get('telegram:poll');
        $webhook = $registry->get('telegram:webhook');

        $this->assertNotNull($sync);
        $this->assertNotNull($poll);
        $this->assertNotNull($webhook);

        $syncOptions    = $sync->signature->options();
        $pollOptions    = $poll->signature->options();
        $webhookOptions = $webhook->signature->options();

        $this->assertArrayHasKey('scope', $syncOptions);
        $this->assertArrayHasKey('tenant', $syncOptions);
        $this->assertArrayHasKey('scope', $pollOptions);
        $this->assertArrayHasKey('tenant', $pollOptions);
        $this->assertArrayHasKey('scope', $webhookOptions);
        $this->assertArrayHasKey('tenant', $webhookOptions);
    }
}
