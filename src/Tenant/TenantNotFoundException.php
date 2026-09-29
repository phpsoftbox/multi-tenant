<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant;

use RuntimeException;
use Throwable;

use function trim;

final class TenantNotFoundException extends RuntimeException
{
    public function __construct(
        private readonly ?string $host = null,
        string $message = 'Tenant is not resolved.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function forHost(string $host): self
    {
        $host = trim($host);
        if ($host === '') {
            return self::forEmptyHost();
        }

        return new self(
            host: $host,
            message: 'Tenant is not resolved for host: ' . $host,
        );
    }

    public static function forEmptyHost(): self
    {
        return new self(
            host: null,
            message: 'Tenant host is empty.',
        );
    }

    /**
     * Арендатор найден, но отключён (`enabled = false`): для HTTP это то же «не найден», сообщение — для логов.
     */
    public static function forDisabledTenant(string $tenantId, ?string $host = null): self
    {
        $host = $host !== null ? trim($host) : null;

        return new self(
            host: $host !== '' ? $host : null,
            message: 'Tenant is disabled: ' . $tenantId,
        );
    }

    public function host(): ?string
    {
        return $this->host;
    }
}
