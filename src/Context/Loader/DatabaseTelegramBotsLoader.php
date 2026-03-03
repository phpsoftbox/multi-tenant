<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Context\Loader;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\Entity\TelegramBotEntityInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantEntityManagerFactoryInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantExtensionLoaderInterface;
use PhpSoftBox\MultiTenant\Entity\Tenant\TelegramBot;
use PhpSoftBox\MultiTenant\Tenant\DefaultTenantEntityManagerFactory;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use RuntimeException;

use function is_array;
use function is_string;
use function is_subclass_of;
use function ltrim;
use function preg_replace;
use function strtolower;
use function trim;
use function usort;

final class DatabaseTelegramBotsLoader implements TenantExtensionLoaderInterface
{
    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $botsByTenant = null;

    private ?EntityManagerInterface $entityManager = null;

    /**
     * @param class-string<TelegramBotEntityInterface> $telegramBotEntityClass
     */
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $telegramBotEntityClass = TelegramBot::class,
        private readonly ?TenantEntityManagerFactoryInterface $entityManagerFactory = null,
        private readonly string $extensionKey = 'telegram.bots',
        private readonly int $extensionPriority = 600,
    ) {
    }

    public function key(): string
    {
        return $this->extensionKey;
    }

    public function supports(TenantRuntimeScope $scope): bool
    {
        return true;
    }

    public function priority(): int
    {
        return $this->extensionPriority;
    }

    public function load(TenantDefinition $tenant, TenantContext $context): mixed
    {
        $bots = $this->databaseBots($tenant->id);
        if ($bots === []) {
            $bots = $this->dataBots($tenant->data);
        }

        $defaultBot = null;
        foreach ($bots as $bot) {
            $isDefault = (bool) ($bot['is_default'] ?? false);
            if ($isDefault) {
                $defaultBot = is_string($bot['code'] ?? null) ? $bot['code'] : null;
                break;
            }
        }

        if ($defaultBot === null) {
            $telegram = $tenant->data['telegram'] ?? null;
            if (is_array($telegram)) {
                $candidate = $telegram['default'] ?? null;
                if (is_string($candidate) && trim($candidate) !== '') {
                    $defaultBot = trim($candidate);
                }

                if ($defaultBot === null) {
                    $candidate = $telegram['bot_code'] ?? null;
                    if (is_string($candidate) && trim($candidate) !== '') {
                        $defaultBot = $this->toBotCode($candidate);
                    }
                }
            }
        }

        if ($defaultBot === null) {
            $candidate = $tenant->data['telegram_default'] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                $defaultBot = trim($candidate);
            }
        }

        return [
            'bots'    => $bots,
            'default' => $defaultBot,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function databaseBots(string $tenantId): array
    {
        if ($this->botsByTenant === null) {
            $this->botsByTenant = $this->loadAllBots();
        }

        return $this->botsByTenant[$tenantId] ?? [];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadAllBots(): array
    {
        $this->assertEntityClass($this->telegramBotEntityClass, TelegramBotEntityInterface::class);

        $repository = $this->entityManager()->repository($this->telegramBotEntityClass);
        if (!$repository instanceof EntityRepositoryInterface) {
            throw new RuntimeException('Репозиторий telegram bot entity должен реализовывать EntityRepositoryInterface.');
        }

        $out = [];
        foreach ($repository->all()->all() as $entity) {
            if (!$entity instanceof TelegramBotEntityInterface) {
                continue;
            }

            if (!$entity->enabled()) {
                continue;
            }

            $tenantId = $entity->tenantId();
            $code     = trim($entity->code());
            $token    = trim($entity->token());

            if ($tenantId <= 0 || $code === '' || $token === '') {
                continue;
            }

            $username = $entity->username();
            if (is_string($username)) {
                $username = trim($username);
                if ($username === '') {
                    $username = null;
                }
            } else {
                $username = null;
            }

            $data = $entity->data();
            if (!is_array($data)) {
                $data = [];
            }

            $out[(string) $tenantId][] = [
                'code'       => $code,
                'token'      => $token,
                'username'   => $username,
                'is_default' => $entity->isDefault(),
                'data'       => $data,
            ];
        }

        foreach ($out as $tenantId => $bots) {
            usort($bots, static function (array $left, array $right): int {
                $leftDefault  = (bool) ($left['is_default'] ?? false);
                $rightDefault = (bool) ($right['is_default'] ?? false);
                if ($leftDefault !== $rightDefault) {
                    return $leftDefault ? -1 : 1;
                }

                return (string) ($left['code'] ?? '') <=> (string) ($right['code'] ?? '');
            });

            $out[$tenantId] = $bots;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function dataBots(array $data): array
    {
        $botsRaw = $data['telegram_bots'] ?? null;
        if (is_array($botsRaw)) {
            $bots = [];
            foreach ($botsRaw as $key => $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (($item['enabled'] ?? true) !== true) {
                    continue;
                }

                $code = $item['code'] ?? (is_string($key) ? $key : null);
                if (!is_string($code) || trim($code) === '') {
                    continue;
                }

                $token = $item['token'] ?? null;
                if (!is_string($token) || trim($token) === '') {
                    continue;
                }

                $username = $item['username'] ?? null;
                if (is_string($username)) {
                    $username = trim($username);
                    if ($username === '') {
                        $username = null;
                    }
                } else {
                    $username = null;
                }

                $botData = $item['data'] ?? [];
                if (!is_array($botData)) {
                    $botData = [];
                }

                $bots[] = [
                    'code'       => trim($code),
                    'token'      => trim($token),
                    'username'   => $username,
                    'is_default' => (bool) ($item['is_default'] ?? false),
                    'data'       => $botData,
                ];
            }

            return $bots;
        }

        $telegram = $data['telegram'] ?? null;
        if (!is_array($telegram) || ($telegram['enabled'] ?? true) !== true) {
            return [];
        }

        $token = $telegram['bot_token'] ?? null;
        if (!is_string($token) || trim($token) === '') {
            return [];
        }

        $code    = null;
        $botCode = $telegram['bot_code'] ?? null;
        if (is_string($botCode) && trim($botCode) !== '') {
            $code = $this->toBotCode($botCode);
        }

        $botName = $telegram['bot_name'] ?? null;
        if (($code === null || $code === '') && is_string($botName) && trim($botName) !== '') {
            $code = $this->toBotCode(ltrim(trim($botName), '@'));
        }

        if ($code === null || $code === '') {
            return [];
        }

        $username = is_string($botName) && trim($botName) !== ''
            ? ltrim(trim($botName), '@')
            : null;

        $welcomeMessage = $telegram['welcome_message'] ?? null;
        $botData        = [];
        if (is_string($welcomeMessage) && trim($welcomeMessage) !== '') {
            $botData['welcome_message'] = trim($welcomeMessage);
        }

        return [[
            'code'       => $code,
            'token'      => trim($token),
            'username'   => $username,
            'is_default' => true,
            'data'       => $botData,
        ]];
    }

    private function entityManager(): EntityManagerInterface
    {
        if ($this->entityManager !== null) {
            return $this->entityManager;
        }

        $factory             = $this->entityManagerFactory ?? new DefaultTenantEntityManagerFactory();
        $this->entityManager = $factory->create(
            connections: $this->connections,
            connectionName: $this->connectionName,
        );

        return $this->entityManager;
    }

    private function assertEntityClass(string $class, string $expectedInterface): void
    {
        if (!is_subclass_of($class, $expectedInterface)) {
            throw new RuntimeException('Entity class должен реализовывать ' . $expectedInterface . ': ' . $class);
        }
    }

    private function toBotCode(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $code = preg_replace('/[^a-z0-9_]+/', '_', $value);
        if (!is_string($code)) {
            return '';
        }

        return trim($code, '_');
    }
}
