<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm\ChangeLog;

use PhpSoftBox\Orm\ChangeLog\ChangeLogDocumentBuilder;
use PhpSoftBox\Orm\ChangeLog\EntityChangeAction;
use PhpSoftBox\Orm\ChangeLog\EntityChangeLoggerInterface;
use PhpSoftBox\Orm\ChangeLog\EntityChangeRecord;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function strtolower;
use function trim;

final class TenantMongoEntityChangeLogger implements EntityChangeLoggerInterface
{
    /**
     * @var array<string, true>
     */
    private readonly array $technicalFields;

    /**
     * @param list<string> $technicalFields
     */
    public function __construct(
        private readonly object $tenantMongo,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $collection = 'entity_changelog',
        array $technicalFields = [
            'created_datetime',
            'updated_datetime',
            'deleted_datetime',
        ],
        private readonly ChangeLogDocumentBuilder $documentBuilder = new ChangeLogDocumentBuilder(),
    ) {
        $this->technicalFields = $this->normalizeTechnicalFields($technicalFields);
    }

    public function log(EntityChangeRecord $record): void
    {
        if ($this->shouldSkipRecord($record)) {
            return;
        }

        try {
            $collection = $this->collection();
            $collection->insertOne($this->documentBuilder->build($record));
        } catch (Throwable $exception) {
            $this->logger?->warning('Не удалось сохранить changelog в tenant MongoDB.', [
                'entity_class' => $record->entityClass,
                'entity_id'    => $record->entityId,
                'exception'    => $exception,
            ]);
        }
    }

    private function collection(): object
    {
        if (!method_exists($this->tenantMongo, 'collection')) {
            throw new RuntimeException('TenantMongoEntityChangeLogger expects provider with collection(string) method.');
        }

        $collection = $this->tenantMongo->collection($this->resolveCollection());
        if (!is_object($collection) || !method_exists($collection, 'insertOne')) {
            throw new RuntimeException('TenantMongoEntityChangeLogger expects collection object with insertOne(array) method.');
        }

        return $collection;
    }

    private function resolveCollection(): string
    {
        $collection = trim($this->collection);

        return $collection !== '' ? $collection : 'entity_changelog';
    }

    private function shouldSkipRecord(EntityChangeRecord $record): bool
    {
        if ($record->action !== EntityChangeAction::Update) {
            return false;
        }

        if ($record->changes === []) {
            return true;
        }

        foreach ($record->changes as $change) {
            if (!is_array($change)) {
                return false;
            }

            $field = $change['field'] ?? null;
            if (!is_string($field) || trim($field) === '') {
                return false;
            }

            if (!isset($this->technicalFields[strtolower(trim($field))])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $fields
     * @return array<string, true>
     */
    private function normalizeTechnicalFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $field) {
            $field = strtolower(trim($field));
            if ($field === '') {
                continue;
            }

            $normalized[$field] = true;
        }

        return $normalized;
    }
}
