<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts\Entity;

use PhpSoftBox\Orm\Contracts\EntityInterface;

interface TelegramBotEntityInterface extends EntityInterface
{
    public function tenantId(): string;

    public function code(): string;

    public function token(): string;

    public function username(): ?string;

    public function isDefault(): bool;

    public function enabled(): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function data(): ?array;
}
