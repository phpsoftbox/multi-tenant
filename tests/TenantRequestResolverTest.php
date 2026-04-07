<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use InvalidArgumentException;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Http\Resolver\ChainTenantRequestResolver;
use PhpSoftBox\MultiTenant\Http\Resolver\HeaderTenantRequestResolver;
use PhpSoftBox\MultiTenant\Http\Resolver\HostTenantRequestResolver;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantNotFoundException;
use PhpSoftBox\MultiTenant\Tests\Support\TestTenantProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderTenantRequestResolver::class)]
#[CoversClass(HostTenantRequestResolver::class)]
#[CoversClass(ChainTenantRequestResolver::class)]
#[CoversMethod(HeaderTenantRequestResolver::class, 'resolve')]
#[CoversMethod(HostTenantRequestResolver::class, 'resolve')]
#[CoversMethod(ChainTenantRequestResolver::class, 'resolve')]
final class TenantRequestResolverTest extends TestCase
{
    /**
     * Проверяет выбор tenant по настроенному заголовку без зависимости от домена и регистра имени заголовка.
     *
     * @see HeaderTenantRequestResolver::resolve()
     */
    #[Test]
    public function resolvesCustomHeader(): void
    {
        $tenant  = $this->tenant('42');
        $request = new ServerRequest('GET', 'https://api.example.test')
            ->withHeader('x-workspace-id', ' 42 ');

        self::assertSame($tenant, new HeaderTenantRequestResolver('X-Workspace-Id')->resolve(
            $request,
            new TestTenantProvider([$tenant]),
        ));
    }

    /**
     * Проверяет, что отсутствие заголовка не вызывает поиск в provider.
     *
     * @see HeaderTenantRequestResolver::resolve()
     */
    #[Test]
    public function missingHeaderSkipsProvider(): void
    {
        $provider = $this->createMock(TenantProviderInterface::class);
        $provider->expects(self::never())->method('findById');

        self::assertNull(new HeaderTenantRequestResolver()->resolve(new ServerRequest('GET', '/'), $provider));
    }

    /**
     * Проверяет, что ошибочное значение заголовка останавливает цепочку, даже если домен известен.
     *
     * @see ChainTenantRequestResolver::resolve()
     * @see HeaderTenantRequestResolver::resolve()
     */
    #[Test]
    #[DataProvider('invalidHeaders')]
    public function invalidHeaderDoesNotFallBack(string|array $value): void
    {
        $provider = $this->createMock(TenantProviderInterface::class);
        $provider->expects(self::never())->method('findByHost');
        $request = new ServerRequest('GET', 'https://tenant.example.test')->withHeader('X-Tenant-Id', $value);

        $this->expectException(TenantNotFoundException::class);
        $this->chain()->resolve($request, $provider);
    }

    public static function invalidHeaders(): iterable
    {
        yield 'unknown identifier' => ['unknown'];
        yield 'empty value' => [''];
        yield 'whitespace' => ['   '];
        yield 'multiple headers' => [['first', 'second']];
        yield 'combined header' => ['first,second'];
    }

    /**
     * Проверяет приоритет заголовка над доменом, указывающим на другого tenant.
     *
     * @see ChainTenantRequestResolver::resolve()
     */
    #[Test]
    public function headerWinsOverAnotherTenantHost(): void
    {
        $headerTenant = $this->tenant('header');
        $hostTenant   = $this->tenant('host');
        $request      = new ServerRequest('GET', 'https://host.example.test')->withHeader('X-Tenant-Id', 'header');

        self::assertSame($headerTenant, $this->chain()->resolve($request, new TestTenantProvider([$headerTenant, $hostTenant])));
    }

    /**
     * Проверяет переход к домену только при отсутствии tenant-заголовка.
     *
     * @see ChainTenantRequestResolver::resolve()
     * @see HostTenantRequestResolver::resolve()
     */
    #[Test]
    public function missingHeaderFallsBackToHost(): void
    {
        $tenant = $this->tenant('host');

        self::assertSame($tenant, $this->chain()->resolve(
            new ServerRequest('GET', 'https://HOST.example.test'),
            new TestTenantProvider([$tenant]),
        ));
    }

    /**
     * Проверяет, что порядок resolver задаёт приоритет и может отдавать предпочтение домену.
     *
     * @see ChainTenantRequestResolver::resolve()
     */
    #[Test]
    public function configuredOrderCanPreferHost(): void
    {
        $tenant  = $this->tenant('host');
        $chain   = new ChainTenantRequestResolver([new HostTenantRequestResolver(), new HeaderTenantRequestResolver()]);
        $request = new ServerRequest('GET', 'https://host.example.test')->withHeader('X-Tenant-Id', 'unknown');

        self::assertSame($tenant, $chain->resolve($request, new TestTenantProvider([$tenant])));
    }

    /**
     * Проверяет null, когда ни один источник цепочки не присутствует в запросе.
     *
     * @see ChainTenantRequestResolver::resolve()
     */
    #[Test]
    public function unresolvedChainReturnsNull(): void
    {
        $chain = new ChainTenantRequestResolver([new HeaderTenantRequestResolver()]);

        self::assertNull($chain->resolve(new ServerRequest('GET', '/'), new TestTenantProvider([])));
    }

    /**
     * Проверяет отказ от некорректного имени HTTP-заголовка при настройке resolver.
     *
     * @see HeaderTenantRequestResolver::__construct()
     */
    #[Test]
    public function invalidHeaderNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HeaderTenantRequestResolver('X Tenant');
    }

    private function chain(): ChainTenantRequestResolver
    {
        return new ChainTenantRequestResolver([new HeaderTenantRequestResolver(), new HostTenantRequestResolver()]);
    }

    private function tenant(string $id): TenantDefinition
    {
        return new TenantDefinition($id, $id, $id . '.example.test', 'tenant');
    }
}
