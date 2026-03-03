<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\MultiTenant\Entity\Auth\Role;
use PhpSoftBox\MultiTenant\Entity\Auth\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
#[CoversClass(Role::class)]
final class AuthEntityDatePointTest extends TestCase
{
    #[Test]
    public function testUserUsesDatePointFields(): void
    {
        $created = DatePoint::now();
        $updated = DatePoint::now();
        $user    = new User(
            id: null,
            name: 'Test User',
            phone: '79990000000',
            email: null,
            status: 'active',
            passwordHash: 'hash',
            authToken: 'token',
            createdDatetime: $created,
            updatedDatetime: $updated,
            deletedDatetime: null,
        );

        $this->assertInstanceOf(DatePoint::class, $user->createdDatetime);
        $this->assertInstanceOf(DatePoint::class, $user->updatedDatetime);
        $this->assertSame($created, $user->createdDatetime);
        $this->assertSame($updated, $user->updatedDatetime);
        $this->assertNull($user->deletedDatetime);
    }

    #[Test]
    public function testRoleUsesDatePointFields(): void
    {
        $created = DatePoint::now();
        $updated = DatePoint::now();
        $role    = new Role(
            id: null,
            name: 'manager',
            label: 'Manager',
            adminAccess: true,
            createdDatetime: $created,
            updatedDatetime: $updated,
        );

        $this->assertInstanceOf(DatePoint::class, $role->createdDatetime);
        $this->assertInstanceOf(DatePoint::class, $role->updatedDatetime);
        $this->assertSame($created, $role->createdDatetime);
        $this->assertSame($updated, $role->updatedDatetime);
    }
}
