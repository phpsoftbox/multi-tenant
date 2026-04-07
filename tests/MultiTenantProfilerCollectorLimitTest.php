<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use InvalidArgumentException;
use PhpSoftBox\MultiTenant\Profiler\MultiTenantProfilerCollector;
use PhpSoftBox\Profiler\ProfileTrace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultiTenantProfilerCollector::class)]
#[CoversMethod(MultiTenantProfilerCollector::class, 'recordEvent')]
#[CoversMethod(MultiTenantProfilerCollector::class, 'collect')]
#[CoversMethod(MultiTenantProfilerCollector::class, 'reset')]
final class MultiTenantProfilerCollectorLimitTest extends TestCase
{
    /**
     * Проверим, что после предела список событий не растёт, счётчик продолжает считать, сводка помечена truncated.
     *
     * @see MultiTenantProfilerCollector::recordEvent()
     * @see MultiTenantProfilerCollector::collect()
     */
    #[Test]
    public function eventsAboveLimitAreDroppedButCounted(): void
    {
        $collector = new MultiTenantProfilerCollector(maxItems: 2);

        for ($index = 0; $index < 5; $index++) {
            $collector->recordEvent('tenant.connection.reuse');
        }

        $data = $collector->collect($this->trace());

        self::assertCount(2, $data['events']);
        self::assertSame(5, $data['summary']['events']);
        self::assertTrue($data['summary']['truncated']);
        self::assertSame(3, $data['summary']['dropped']);
    }

    /**
     * Проверим, что reset() очищает и список, и признак обрезки.
     *
     * @see MultiTenantProfilerCollector::reset()
     */
    #[Test]
    public function resetClearsEventsAndTruncation(): void
    {
        $collector = new MultiTenantProfilerCollector(maxItems: 1);

        $collector->recordEvent('tenant.connection.activate');
        $collector->recordEvent('tenant.connection.deactivate');

        $collector->reset();
        $data = $collector->collect($this->trace());

        self::assertSame([], $data['events']);
        self::assertFalse($data['summary']['truncated']);
        self::assertSame(0, $data['summary']['dropped']);
    }

    /**
     * Проверим, что отрицательный предел отклоняется.
     *
     * @see MultiTenantProfilerCollector::recordEvent()
     */
    #[Test]
    public function rejectsNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MultiTenantProfilerCollector(maxItems: -1);
    }

    private function trace(): ProfileTrace
    {
        return new ProfileTrace('id', 'test', 'test');
    }
}
