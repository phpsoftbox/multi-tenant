<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Profiler;

use PhpSoftBox\Profiler\ProfilerExtensionInterface;
use PhpSoftBox\Profiler\ProfilerRegistryInterface;

final class MultiTenantProfilerExtension implements ProfilerExtensionInterface
{
    private MultiTenantProfilerCollector $collector;

    public function __construct(?MultiTenantProfilerCollector $collector = null)
    {
        $this->collector = $collector ?? new MultiTenantProfilerCollector();
    }

    public function collector(): MultiTenantProfilerCollector
    {
        return $this->collector;
    }

    public function register(ProfilerRegistryInterface $registry): void
    {
        $registry->addCollector($this->collector);
    }
}
