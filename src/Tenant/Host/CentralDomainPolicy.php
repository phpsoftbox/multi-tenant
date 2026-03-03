<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant\Host;

use function is_string;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final readonly class CentralDomainPolicy
{
    /**
     * @param list<string> $domains
     */
    public function __construct(
        private array $domains = [],
    ) {
    }

    public function isCentral(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }

        foreach ($this->domains as $pattern) {
            if (!is_string($pattern)) {
                continue;
            }

            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }

            if ($pattern === $host) {
                return true;
            }

            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 2);
                if ($suffix !== '' && str_ends_with($host, '.' . $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
