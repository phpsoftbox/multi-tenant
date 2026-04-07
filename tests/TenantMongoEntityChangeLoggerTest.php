<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use DateTimeImmutable;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\TenantMongoEntityChangeLogger;
use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeContext;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

#[CoversClass(TenantMongoEntityChangeLogger::class)]
final class TenantMongoEntityChangeLoggerTest extends TestCase
{
    #[Test]
    public function logWritesDocumentIntoTenantMongoCollection(): void
    {
        $collection = new RecordingMongoCollection();

        $mongo = new RecordingTenantMongoProvider($collection);

        $logger = new TenantMongoEntityChangeLogger($mongo, collection: 'custom_changelog');

        $logger->log($this->record(changes: [
            ['field' => 'name', 'before' => 'old', 'after' => 'new'],
        ]));

        self::assertSame('custom_changelog', $mongo->requestedCollection);
        self::assertCount(1, $collection->documents);
        self::assertSame('tenant.users', $collection->documents[0]['entity_class'] ?? null);
        self::assertSame('update', $collection->documents[0]['action'] ?? null);
    }

    #[Test]
    public function logSkipsUpdateWhenOnlyTechnicalFieldsChanged(): void
    {
        $collection = new RecordingMongoCollection();

        $logger = new TenantMongoEntityChangeLogger(new RecordingTenantMongoProvider($collection));

        $logger->log($this->record(changes: [
            ['field' => 'updated_datetime', 'before' => '2026-01-01', 'after' => '2026-01-02'],
        ]));

        self::assertSame([], $collection->documents);
    }

    #[Test]
    public function logUsesConfiguredTechnicalFields(): void
    {
        $collection = new RecordingMongoCollection();

        $logger = new TenantMongoEntityChangeLogger(
            new RecordingTenantMongoProvider($collection),
            technicalFields: ['updated_by'],
        );

        $logger->log($this->record(changes: [
            ['field' => 'updated_by', 'before' => 1, 'after' => 2],
        ]));

        self::assertSame([], $collection->documents);
    }

    #[Test]
    public function logReportsFailureToLogger(): void
    {
        $psrLogger = new RecordingPsrLogger();

        $logger = new TenantMongoEntityChangeLogger(
            new RecordingTenantMongoProvider(new FailingMongoCollection()),
            logger: $psrLogger,
        );

        $logger->log($this->record(changes: [
            ['field' => 'name', 'before' => 'old', 'after' => 'new'],
        ]));

        self::assertSame('Не удалось сохранить changelog в tenant MongoDB.', $psrLogger->warnings[0]['message'] ?? null);
        self::assertSame('tenant.users', $psrLogger->warnings[0]['context']['entity_class'] ?? null);
    }

    /**
     * @param list<array<string, mixed>> $changes
     */
    private function record(array $changes): EntityChangeRecord
    {
        return new EntityChangeRecord(
            action: EntityChangeAction::Update,
            entityClass: 'tenant.users',
            entityId: 15,
            before: ['name' => 'old'],
            after: ['name' => 'new'],
            changes: $changes,
            context: new EntityChangeContext(initiatorId: 7, initiatorType: 'user'),
            occurredAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }
}

final class RecordingTenantMongoProvider
{
    public ?string $requestedCollection = null;

    public function __construct(
        private readonly object $collection,
    ) {
    }

    public function collection(string $collection): object
    {
        $this->requestedCollection = $collection;

        return $this->collection;
    }
}

final class RecordingMongoCollection
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $documents = [];

    /**
     * @param array<string, mixed> $document
     */
    public function insertOne(array $document): void
    {
        $this->documents[] = $document;
    }
}

final class FailingMongoCollection
{
    /**
     * @param array<string, mixed> $_document
     */
    public function insertOne(array $_document): void
    {
        throw new RuntimeException('write failed');
    }
}

final class RecordingPsrLogger extends AbstractLogger
{
    /**
     * @var list<array{message: string, context: array<string, mixed>}>
     */
    public array $warnings = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if ($level !== 'warning') {
            return;
        }

        $this->warnings[] = [
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
