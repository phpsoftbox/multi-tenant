<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator;

use PhpSoftBox\MultiTenant\Orm\ChangeLog\EntityChangeInitiatorResolverInterface;

final class ChainEntityChangeInitiatorResolver implements EntityChangeInitiatorResolverInterface
{
    /**
     * @var list<EntityChangeInitiatorResolverInterface>
     */
    private readonly array $resolvers;

    /**
     * @param iterable<EntityChangeInitiatorResolverInterface> $resolvers
     */
    public function __construct(iterable $resolvers)
    {
        $resolved = [];
        foreach ($resolvers as $resolver) {
            $resolved[] = $resolver;
        }

        $this->resolvers = $resolved;
    }

    public function resolve(): int|string|null
    {
        foreach ($this->resolvers as $resolver) {
            $resolved = $resolver->resolve();
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }
}
