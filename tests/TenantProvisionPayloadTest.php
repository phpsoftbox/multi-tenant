<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use InvalidArgumentException;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantProvisionPayload::class)]
final class TenantProvisionPayloadTest extends TestCase
{
    #[Test]
    public function testConstructorRejectsEmptyTenantId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID обязателен для provisioning.');

        new TenantProvisionPayload('   ');
    }

    #[Test]
    public function testToArrayAndQueuePayloadContainExpectedFields(): void
    {
        $payload = new TenantProvisionPayload(
            tenantId: 'tenant-1',
            templateTenantId: 'template-1',
            ownerPhone: '+79991234567',
            ownerName: 'Owner',
            ownerEmail: 'owner@example.test',
            confirmOwnerPhone: false,
            extra: ['source' => 'test'],
        );

        $this->assertSame(
            [
                'type'                => TenantProvisionPayload::JOB_TYPE,
                'tenant_id'           => 'tenant-1',
                'template_tenant_id'  => 'template-1',
                'owner_phone'         => '+79991234567',
                'owner_name'          => 'Owner',
                'owner_email'         => 'owner@example.test',
                'confirm_owner_phone' => false,
                'extra'               => ['source' => 'test'],
            ],
            $payload->toArray(),
        );

        $this->assertSame(
            [
                '_mt_job'          => TenantProvisionPayload::JOB_TYPE,
                'tenant_provision' => $payload->toArray(),
            ],
            $payload->toQueuePayload(),
        );
    }

    #[Test]
    public function testFromArrayNormalizesValues(): void
    {
        $payload = TenantProvisionPayload::fromArray([
            'tenant_id'           => '  tenant-2  ',
            'template_tenant_id'  => '  template-2  ',
            'owner_phone'         => '  +79990000000  ',
            'owner_name'          => '  Owner  ',
            'owner_email'         => '  owner2@example.test  ',
            'confirm_owner_phone' => 'not-bool',
            'extra'               => 'not-array',
        ]);

        $this->assertSame('tenant-2', $payload->tenantId);
        $this->assertSame('template-2', $payload->templateTenantId);
        $this->assertSame('+79990000000', $payload->ownerPhone);
        $this->assertSame('Owner', $payload->ownerName);
        $this->assertSame('owner2@example.test', $payload->ownerEmail);
        $this->assertTrue($payload->confirmOwnerPhone);
        $this->assertSame([], $payload->extra);
    }

    #[Test]
    public function testFromArrayThrowsWithoutTenantId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue payload tenant.provision должен содержать tenant_id.');

        TenantProvisionPayload::fromArray([]);
    }
}
