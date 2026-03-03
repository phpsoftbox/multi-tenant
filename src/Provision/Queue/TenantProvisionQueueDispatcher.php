<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Queue;

use PhpSoftBox\MultiTenant\Provision\TenantProvisionPayload;
use PhpSoftBox\Queue\QueueInterface;
use PhpSoftBox\Queue\QueueJob;

final readonly class TenantProvisionQueueDispatcher
{
    public function __construct(
        private QueueInterface $queue,
    ) {
    }

    public function dispatch(TenantProvisionPayload $payload, int $priority = 0): string
    {
        $job = QueueJob::fromPayload(
            payload: [
                'tenant_id' => $payload->tenantId,
                'payload'   => $payload->toQueuePayload(),
            ],
            priority: $priority,
        );

        $this->queue->push($job);

        return $job->id();
    }
}
