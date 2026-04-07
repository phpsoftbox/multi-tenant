<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm\ChangeLog;

use PhpSoftBox\MultiTenant\Context\TenantContextResolver;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContext;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContextResolverInterface;
use Psr\Http\Message\ServerRequestInterface;

use function strtoupper;
use function trim;

final class TenantEntityChangeContextResolver implements EntityChangeContextResolverInterface
{
    public function __construct(
        private readonly TenantContextResolver $tenantResolver,
        private readonly ?EntityChangeInitiatorResolverInterface $initiatorResolver = null,
        private readonly ?ServerRequestInterface $request = null,
    ) {
    }

    public function resolve(): EntityChangeContext
    {
        $initiatorId = $this->initiatorResolver?->resolve();

        return new EntityChangeContext(
            initiatorId: $initiatorId,
            initiatorType: $initiatorId !== null ? 'user' : 'system',
            metadata: $this->metadata(),
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private function metadata(): array
    {
        $metadata = $this->tenantMetadata();

        if ($this->request instanceof ServerRequestInterface) {
            $metadata = [...$metadata, ...$this->requestMetadata($this->request)];
        }

        return $metadata;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function tenantMetadata(): array
    {
        $tenant = $this->tenantResolver->get();
        if ($tenant === null) {
            return [];
        }

        $tenantId = trim($tenant->id);
        if ($tenantId === '') {
            return [];
        }

        return ['tenant_id' => $tenantId];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function requestMetadata(ServerRequestInterface $request): array
    {
        $metadata = [];

        $method = trim(strtoupper($request->getMethod()));
        if ($method !== '') {
            $metadata['request_method'] = $method;
        }

        $path = trim($request->getUri()->getPath());
        if ($path !== '') {
            $metadata['request_path'] = $path;
        }

        return $metadata;
    }
}
