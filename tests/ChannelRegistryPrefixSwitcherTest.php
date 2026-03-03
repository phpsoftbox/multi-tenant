<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Broadcaster\Channel\ChannelRegistry;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Switcher\ChannelRegistryPrefixSwitcher;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(ChannelRegistryPrefixSwitcher::class)]
final class ChannelRegistryPrefixSwitcherTest extends TestCase
{
    #[Test]
    public function testActivatePrefixesRulesAndDeactivateRestoresRules(): void
    {
        $registry = new ChannelRegistry();

        $registry->channel('private.user.{userId}', static fn (string $userId): bool => $userId === '42');

        $request  = $this->createMock(ServerRequestInterface::class);
        $context  = $this->context();
        $switcher = new ChannelRegistryPrefixSwitcher($registry);

        $this->assertTrue($registry->authorize('private.user.42', $request)->authorized());

        $switcher->activate('tenant.alpha.', $context);

        $this->assertFalse($registry->authorize('private.user.42', $request)->authorized());
        $this->assertTrue($registry->authorize('tenant.alpha.private.user.42', $request)->authorized());

        $switcher->deactivate($context);

        $this->assertTrue($registry->authorize('private.user.42', $request)->authorized());
        $this->assertFalse($registry->authorize('tenant.alpha.private.user.42', $request)->authorized());
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-alpha', 'Tenant Alpha', null, 'tenant'));
    }
}
