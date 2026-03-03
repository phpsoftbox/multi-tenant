<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Switcher;

use PhpSoftBox\Broadcaster\Channel\ChannelRegistry;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\BroadcastChannelSwitcherInterface;

use function array_pop;
use function is_callable;
use function is_string;
use function ltrim;
use function str_starts_with;
use function trim;

final class ChannelRegistryPrefixSwitcher implements BroadcastChannelSwitcherInterface
{
    /** @var list<array<int, array{pattern:string,regex:string,params:list<string>,authorizer:callable}>> */
    private array $stack = [];

    public function __construct(
        private readonly ChannelRegistry $registry,
    ) {
    }

    public function activate(string $channelPrefix, TenantContext $context): void
    {
        $channelPrefix = trim($channelPrefix);
        $currentRules  = $this->registry->rules();

        $this->stack[] = $currentRules;

        if ($channelPrefix === '') {
            return;
        }

        $prefixed = [];
        foreach ($currentRules as $rule) {
            $pattern    = $rule['pattern'];
            $authorizer = $rule['authorizer'];

            if (!is_string($pattern) || $pattern === '' || !is_callable($authorizer)) {
                continue;
            }

            $pattern = str_starts_with($pattern, $channelPrefix)
                ? $pattern
                : $channelPrefix . ltrim($pattern, '.');

            $prefixed[] = [
                'pattern'    => $pattern,
                'authorizer' => $authorizer,
            ];
        }

        $this->replaceRules($prefixed);
    }

    public function deactivate(TenantContext $context): void
    {
        $previous = $this->stack !== [] ? array_pop($this->stack) : null;
        if ($previous === null) {
            return;
        }

        $rules = [];
        foreach ($previous as $rule) {
            $pattern    = $rule['pattern'] ?? null;
            $authorizer = $rule['authorizer'] ?? null;

            if (!is_string($pattern) || $pattern === '' || !is_callable($authorizer)) {
                continue;
            }

            $rules[] = [
                'pattern'    => $pattern,
                'authorizer' => $authorizer,
            ];
        }

        $this->replaceRules($rules);
    }

    /**
     * @param list<array{pattern:string,authorizer:callable}> $rules
     */
    private function replaceRules(array $rules): void
    {
        $this->forceClearRules();

        foreach ($rules as $rule) {
            $this->registry->channel($rule['pattern'], $rule['authorizer']);
        }
    }

    private function forceClearRules(): void
    {
        $this->registry->clear();
    }
}
