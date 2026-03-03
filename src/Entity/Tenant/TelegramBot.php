<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Entity\Tenant;

use PhpSoftBox\MultiTenant\Contracts\Entity\TelegramBotEntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\GeneratedValue;
use PhpSoftBox\Orm\Metadata\Attributes\Id;
use Ramsey\Uuid\UuidInterface;

#[Entity(table: 'telegram_bots', connection: 'default')]
class TelegramBot implements TelegramBotEntityInterface
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'auto')]
        #[Column(type: 'int')]
        public ?int $id,
        #[Column(name: 'tenant_id', type: 'int')]
        public int $tenantId,
        #[Column(type: 'string', length: 100)]
        public string $code,
        #[Column(type: 'string', length: 255)]
        public string $token,
        #[Column(type: 'string', length: 255, nullable: true)]
        public ?string $username,
        #[Column(name: 'is_default', type: 'bool')]
        public bool $isDefault,
        #[Column(name: 'is_enabled', type: 'bool')]
        public bool $enabled,
        #[Column(type: 'json', nullable: true)]
        public ?array $data,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function username(): ?string
    {
        return $this->username;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function data(): ?array
    {
        return $this->data;
    }
}
