<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Queue;

use InvalidArgumentException;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionRunnerInterface;
use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;

use function is_array;

final readonly class TenantProvisionQueueJobHandler
{
    public function __construct(
        private TenantProvisionRunnerInterface $runner,
    ) {
    }

    public function supports(mixed $payload): bool
    {
        return is_array($payload)
            && ($payload['_mt_job'] ?? null) === TenantProvisionPayload::JOB_TYPE;
    }

    public function handle(mixed $payload): void
    {
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Tenant provision payload должен быть массивом.');
        }

        $raw = $payload['tenant_provision'] ?? null;
        if (!is_array($raw)) {
            throw new InvalidArgumentException('Tenant provision payload не содержит tenant_provision.');
        }

        $this->runner->run(TenantProvisionPayload::fromArray($raw));
    }
}
