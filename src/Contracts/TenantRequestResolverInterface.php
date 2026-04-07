<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

interface TenantRequestResolverInterface
{
    /**
     * Возвращает null только при отсутствии своего источника tenant в запросе.
     * Переданное, но некорректное или неизвестное значение завершает поиск исключением.
     * Provider передаётся middleware после reload, чтобы вся цепочка использовала один источник данных.
     *
     * @throws TenantNotFoundException
     */
    public function resolve(ServerRequestInterface $request, TenantProviderInterface $tenants): ?TenantDefinition;
}
