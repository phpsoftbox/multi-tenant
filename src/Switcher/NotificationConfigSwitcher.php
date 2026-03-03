<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Switcher;

use PhpSoftBox\Config\Contracts\ConfigMutableInterface;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\NotificationConfigSwitcherInterface;

use function array_is_list;
use function array_merge;
use function array_pop;
use function is_array;
use function is_int;
use function is_string;
use function trim;

final class NotificationConfigSwitcher implements NotificationConfigSwitcherInterface
{
    /** @var list<array<string, mixed>> */
    private array $stack = [];

    public function __construct(
        private readonly ConfigMutableInterface $config,
    ) {
    }

    public function activate(array $overrides, TenantContext $context): void
    {
        $flat = $this->flatten($overrides);

        $snapshot = [];
        foreach ($flat as $key => $value) {
            $snapshot[$key] = $this->config->get($key);
            $this->config->override($key, $value);
        }

        $this->stack[] = $snapshot;
    }

    public function deactivate(TenantContext $context): void
    {
        $snapshot = $this->stack !== [] ? array_pop($this->stack) : null;
        if ($snapshot === null) {
            return;
        }

        foreach ($snapshot as $key => $value) {
            $this->config->override($key, $value);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function flatten(array $overrides, string $prefix = ''): array
    {
        $flat = [];

        foreach ($overrides as $key => $value) {
            $segment = $this->normalizeKeySegment($key);
            if ($segment === null) {
                continue;
            }

            $path = $prefix !== '' ? $prefix . '.' . $segment : $segment;
            if (is_array($value) && $value !== [] && !array_is_list($value)) {
                $flat = array_merge($flat, $this->flatten($value, $path));
                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    private function normalizeKeySegment(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $key = trim((string) $value);

        return $key !== '' ? $key : null;
    }
}
