<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Switcher\PushrConfigSwitcher;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PushrConfigSwitcher::class)]
final class PushrConfigSwitcherTest extends TestCase
{
    #[Test]
    public function testActivateOverridesPushrConfigAndDeactivateRestores(): void
    {
        $config = new Config(
            sources: [[
                'pushr' => [
                    'app_id' => 'app-old',
                    'secret' => 'secret-old',
                ],
            ]],
            readOnly: true,
        );

        $switcher = new PushrConfigSwitcher($config->mutable());
        $context  = $this->context();

        $switcher->activate([
            'app_id' => 'app-new',
            'secret' => 'secret-new',
        ], $context);

        $this->assertSame('app-new', $config->get('pushr.app_id'));
        $this->assertSame('secret-new', $config->get('pushr.secret'));

        $switcher->deactivate($context);

        $this->assertSame('app-old', $config->get('pushr.app_id'));
        $this->assertSame('secret-old', $config->get('pushr.secret'));
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-alpha', 'Tenant Alpha', null, 'tenant'));
    }
}
