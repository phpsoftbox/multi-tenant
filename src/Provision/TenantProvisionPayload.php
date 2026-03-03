<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision;

use InvalidArgumentException;

use function is_array;
use function is_bool;
use function is_string;
use function trim;

final readonly class TenantProvisionPayload
{
    public const JOB_TYPE = 'tenant.provision';

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $tenantId,
        public ?string $templateTenantId = null,
        public ?string $ownerPhone = null,
        public ?string $ownerName = null,
        public ?string $ownerEmail = null,
        public bool $confirmOwnerPhone = true,
        public array $extra = [],
    ) {
        $tenantId = trim($this->tenantId);
        if ($tenantId === '') {
            throw new InvalidArgumentException('Tenant ID обязателен для provisioning.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type'                => self::JOB_TYPE,
            'tenant_id'           => $this->tenantId,
            'template_tenant_id'  => $this->templateTenantId,
            'owner_phone'         => $this->ownerPhone,
            'owner_name'          => $this->ownerName,
            'owner_email'         => $this->ownerEmail,
            'confirm_owner_phone' => $this->confirmOwnerPhone,
            'extra'               => $this->extra,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toQueuePayload(): array
    {
        return [
            '_mt_job'          => self::JOB_TYPE,
            'tenant_provision' => $this->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $tenantId = self::normalizeString($payload['tenant_id'] ?? null);
        if ($tenantId === null) {
            throw new InvalidArgumentException('Queue payload tenant.provision должен содержать tenant_id.');
        }

        $extra = $payload['extra'] ?? [];
        if (!is_array($extra)) {
            $extra = [];
        }

        $confirmOwnerPhone = $payload['confirm_owner_phone'] ?? true;
        if (!is_bool($confirmOwnerPhone)) {
            $confirmOwnerPhone = true;
        }

        return new self(
            tenantId: $tenantId,
            templateTenantId: self::normalizeString($payload['template_tenant_id'] ?? null),
            ownerPhone: self::normalizeString($payload['owner_phone'] ?? null),
            ownerName: self::normalizeString($payload['owner_name'] ?? null),
            ownerEmail: self::normalizeString($payload['owner_email'] ?? null),
            confirmOwnerPhone: $confirmOwnerPhone,
            extra: $extra,
        );
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
