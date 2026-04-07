<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Profiler;

use InvalidArgumentException;
use PhpSoftBox\Profiler\ProfilerCollectorInterface;
use PhpSoftBox\Profiler\ProfileTrace;

use function array_values;
use function count;
use function round;

final class MultiTenantProfilerCollector implements ProfilerCollectorInterface
{
    public const int DEFAULT_MAX_ITEMS = 5000;

    private int $events = 0;
    private int $errors = 0;

    /**
     * @var list<array<string, mixed>>
     */
    private array $items = [];

    private int $droppedItems = 0;

    /**
     * @param int $maxItems предел числа событий в списке; после него растут только счётчики
     */
    public function __construct(
        private readonly int $maxItems = self::DEFAULT_MAX_ITEMS,
    ) {
        if ($maxItems < 0) {
            throw new InvalidArgumentException('Max items must not be negative.');
        }
    }

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

        if (count($this->items) >= $this->maxItems) {
            $this->droppedItems++;

            return;
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
                'events'    => $this->events,
                'errors'    => $this->errors,
                'truncated' => $this->droppedItems > 0,
                'dropped'   => $this->droppedItems,
            ],
            'events' => array_values($this->items),
        ];
    }

    public function reset(): void
    {
        $this->events = 0;
        $this->errors = 0;
        $this->items  = [];

        $this->droppedItems = 0;
    }
}
