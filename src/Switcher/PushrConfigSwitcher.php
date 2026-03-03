<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Switcher;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\PushrRegistrySwitcherInterface;
use ReflectionProperty;

use function array_pop;
use function is_string;
use function trim;

final class PushrConfigSwitcher implements PushrRegistrySwitcherInterface
{
    /** @var list<array{app_id:mixed,secret:mixed}> */
    private array $stack = [];

    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function activate(array $credentials, TenantContext $context): void
    {
        $this->stack[] = [
            'app_id' => $this->config->get('pushr.app_id'),
            'secret' => $this->config->get('pushr.secret'),
        ];

        $appId  = $this->normalizeString($credentials['app_id'] ?? null);
        $secret = $this->normalizeString($credentials['secret'] ?? null);

        $this->setConfigValue('pushr.app_id', $appId);
        $this->setConfigValue('pushr.secret', $secret);
    }

    public function deactivate(TenantContext $context): void
    {
        $previous = $this->stack !== [] ? array_pop($this->stack) : null;
        if ($previous === null) {
            return;
        }

        $this->setConfigValue('pushr.app_id', $previous['app_id']);
        $this->setConfigValue('pushr.secret', $previous['secret']);
    }

    private function setConfigValue(string $key, mixed $value): void
    {
        $readOnly = new ReflectionProperty(Config::class, 'readOnly');

        $readOnly->setAccessible(true);

        $wasReadOnly = (bool) $readOnly->getValue($this->config);
        if ($wasReadOnly) {
            $readOnly->setValue($this->config, false);
        }

        try {
            $this->config->set($key, $value);
        } finally {
            if ($wasReadOnly) {
                $readOnly->setValue($this->config, true);
            }
        }
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
