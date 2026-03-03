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
        $user = new User();

        $this->assertInstanceOf(DatePoint::class, $user->createdDatetime);
        $this->assertInstanceOf(DatePoint::class, $user->updatedDatetime);
        $this->assertNull($user->deletedDatetime);
    }

    #[Test]
    public function testRoleUsesDatePointFields(): void
    {
        $role = new Role();

        $this->assertInstanceOf(DatePoint::class, $role->createdDatetime);
        $this->assertInstanceOf(DatePoint::class, $role->updatedDatetime);
    }
}
