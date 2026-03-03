<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Pushr;

use PhpSoftBox\Broadcaster\Contracts\PushrRegistrySourceInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use RuntimeException;

use function array_key_exists;
use function is_array;
use function is_string;
use function trim;

final readonly class TenantPushrRegistrySource implements PushrRegistrySourceInterface
{
    public function __construct(
        private TenantSelector $selector,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    public function apps(array $options = []): array
    {
        $tenant = $options['tenant'] ?? 'all';
        $tenant = is_string($tenant) ? trim($tenant) : '';
        if ($tenant === '') {
            $tenant = 'all';
        }

        $tenants = $this->selector->select($tenant, true);
        $apps    = [];

        foreach ($tenants as $tenantDefinition) {
            [$appId, $secret] = $this->resolvePushrCredentials($tenantDefinition);
            if ($appId === null || $secret === null) {
                throw new RuntimeException(
                    'У tenant "' . $tenantDefinition->id . '" не настроены pushr_app_id/pushr_secret.',
                );
            }

            if (isset($apps[$appId]) && $apps[$appId] !== $secret) {
                throw new RuntimeException(
                    'Конфликт Pushr app_id "' . $appId . '" у tenant "' . $tenantDefinition->id . '".',
                );
            }

            $apps[$appId] = $secret;
        }

        return $apps;
    }

    /**
     * @return array{0:?string, 1:?string}
     */
    private function resolvePushrCredentials(TenantDefinition $tenant): array
    {
        $appId = $this->normalizeString($tenant->pushrAppId)
            ?? $this->dataString($tenant->data, 'pushr', 'app_id')
            ?? $this->dataString($tenant->data, 'pushr_app_id');

        $secret = $this->normalizeString($tenant->pushrSecret)
            ?? $this->dataString($tenant->data, 'pushr', 'secret')
            ?? $this->dataString($tenant->data, 'pushr_secret');

        return [$appId, $secret];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function dataString(array $data, string ...$path): ?string
    {
        if ($path === []) {
            return null;
        }

        $cursor = $data;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $this->normalizeString($cursor);
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
