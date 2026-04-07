<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Profiler;

use PhpSoftBox\Profiler\ProfilerCollectorInterface;
use PhpSoftBox\Profiler\ProfileTrace;

use function array_values;
use function round;

final class MultiTenantProfilerCollector implements ProfilerCollectorInterface
{
    private int $events = 0;
    private int $errors = 0;

    /**
     * @var list<array<string, mixed>>
     */
    private array $items = [];

    public function key(): string
    {
        return 'multi_tenant';
    }

    /**
     * @param array<string, mixed> $tags
     */
    public function recordEvent(
        string $event,
        array $tags = [],
        ?float $durationMs = null,
        bool $failed = false,
        ?string $exceptionClass = null,
    ): void {
        $this->events++;

        if ($failed) {
            $this->errors++;
        }

        $this->items[] = [
            'event'           => $event,
            'tags'            => $tags,
            'duration_ms'     => $durationMs !== null ? round($durationMs, 3) : null,
            'failed'          => $failed,
            'exception_class' => $exceptionClass,
        ];
    }

    public function collect(ProfileTrace $trace): array
    {
        return [
            'summary' => [
                'events' => $this->events,
                'errors' => $this->errors,
            ],
            'events' => array_values($this->items),
        ];
    }

    public function reset(): void
    {
        $this->events = 0;
        $this->errors = 0;
        $this->items  = [];
    }
}
