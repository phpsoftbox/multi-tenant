<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\MultiTenant\Contracts\TenantProvisionCommandRunnerInterface;

final class RecordingProvisionCommandRunner implements TenantProvisionCommandRunnerInterface
{
    /** @var list<list<string>> */
    public array $commands = [];

    public function run(array $arguments): void
    {
        $this->commands[] = $arguments;
    }
}
