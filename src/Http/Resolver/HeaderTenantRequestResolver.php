<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Http\Resolver;

use InvalidArgumentException;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantRequestResolverInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

use function count;
use function preg_match;
use function str_contains;
use function trim;

final readonly class HeaderTenantRequestResolver implements TenantRequestResolverInterface
{
    public function __construct(
        private string $headerName = 'X-Tenant-Id',
    ) {
        if (preg_match('/^[!#$%&\x27*+.^_`|~0-9A-Za-z-]+$/D', $headerName) !== 1) {
            throw new InvalidArgumentException('Tenant header name must be a valid HTTP field name.');
        }
    }

    public function resolve(ServerRequestInterface $request, TenantProviderInterface $tenants): ?TenantDefinition
    {
        if (!$request->hasHeader($this->headerName)) {
            return null;
        }

        $values = $request->getHeader($this->headerName);
        if (count($values) !== 1 || str_contains($values[0], ',')) {
            throw new TenantNotFoundException(message: 'Tenant header must contain exactly one identifier.');
        }

        $id = trim($values[0]);
        if ($id === '') {
            throw new TenantNotFoundException(message: 'Tenant header is empty.');
        }

        return $tenants->findById($id)
            ?? throw new TenantNotFoundException(message: 'Tenant is not resolved from header: ' . $this->headerName);
    }
}
