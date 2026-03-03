<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Telegram;

use Closure;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Bot\TelegramBot;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use PhpSoftBox\Telegram\Bot\UpdateHandlerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function array_key_first;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function ltrim;
use function preg_replace;
use function strtolower;
use function trim;

final readonly class TenantTelegramBotRegistryFactory
{
    public function __construct(
        private TelegramBotRegistry $coreRegistry,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private ?Closure $tokenResolver = null,
        private ?Closure $handlerResolver = null,
    ) {
    }

    public function create(TenantDefinition $tenant): TelegramBotRegistry
    {
        $definitions = $this->normalizeBots($tenant->data);

        $bots = [];
        foreach ($definitions as $item) {
            $code  = $item['code'];
            $token = $item['token'];

            $client  = new TelegramClient(
                token: $token,
                httpClient: $this->httpClient,
                requestFactory: $this->requestFactory,
                streamFactory: $this->streamFactory,
            );
            $handler = $this->resolveHandler($code, $client, $tenant);

            $bots[$code] = new TelegramBot(
                name: $code,
                token: $token,
                client: $client,
                handler: $handler,
            );
        }

        $defaultBot = $this->resolveDefaultBot($tenant->data, $definitions, $bots);

        return new TelegramBotRegistry(
            defaultBot: $defaultBot,
            bots: $bots,
        );
    }

    private function resolveHandler(string $code, TelegramClient $client, TenantDefinition $tenant): UpdateHandlerInterface
    {
        if ($this->handlerResolver !== null) {
            $resolved = ($this->handlerResolver)($code, $client, $tenant);
            if ($resolved instanceof UpdateHandlerInterface) {
                return $resolved;
            }
        }

        return $this->coreRegistry->bot($code)?->handler() ?? $this->coreRegistry->handler();
    }

    /**
     * @param array<string, mixed> $tenantData
     *
     * @return list<array{code:string,token:string,is_default:bool}>
     */
    private function normalizeBots(array $tenantData): array
    {
        $allowedBots = $this->coreRegistry->names();
        $botsRaw     = $tenantData['telegram_bots'] ?? null;
        if (!is_array($botsRaw) && isset($tenantData['telegram']) && is_array($tenantData['telegram'])) {
            $botsRaw = $tenantData['telegram']['bots'] ?? null;
        }

        if (is_array($botsRaw)) {
            $out = [];

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
                $code = trim($code);
                if (!in_array($code, $allowedBots, true)) {
                    continue;
                }

                $token = $item['token'] ?? null;
                if (!is_string($token)) {
                    continue;
                }

                $token = $this->resolveToken($token);
                if ($token === null) {
                    continue;
                }

                $out[$code] = [
                    'code'       => $code,
                    'token'      => $token,
                    'is_default' => (bool) ($item['is_default'] ?? false),
                ];
            }

            return array_values($out);
        }

        $telegram = $tenantData['telegram'] ?? null;
        if (!is_array($telegram)) {
            return [];
        }

        if (($telegram['enabled'] ?? true) !== true) {
            return [];
        }

        $botName  = $telegram['bot_name'] ?? null;
        $botCode  = $telegram['bot_code'] ?? null;
        $tokenRaw = $telegram['bot_token'] ?? null;
        if (!is_string($tokenRaw)) {
            return [];
        }

        $token = $this->resolveToken($tokenRaw);
        if ($token === null) {
            return [];
        }

        $code = null;
        if (is_string($botCode) && trim($botCode) !== '') {
            $code = $this->toBotCode($botCode);
        }

        if (($code === null || $code === '') && is_string($botName) && trim($botName) !== '') {
            $code = $this->toBotCode(ltrim(trim($botName), '@'));
        }

        if ($code === null || $code === '') {
            return [];
        }
        if (!in_array($code, $allowedBots, true)) {
            return [];
        }

        return [[
            'code'       => $code,
            'token'      => $token,
            'is_default' => true,
        ]];
    }

    /**
     * @param array<string, mixed> $tenantData
     * @param list<array{code:string,token:string,is_default:bool}> $definitions
     * @param array<string, TelegramBot> $bots
     */
    private function resolveDefaultBot(array $tenantData, array $definitions, array $bots): string
    {
        if (isset($tenantData['telegram']) && is_array($tenantData['telegram'])) {
            $candidate = $tenantData['telegram']['default'] ?? null;
            if (is_string($candidate) && trim($candidate) !== '' && isset($bots[trim($candidate)])) {
                return trim($candidate);
            }
        }

        $candidate = $tenantData['telegram_default'] ?? null;
        if (is_string($candidate) && trim($candidate) !== '' && isset($bots[trim($candidate)])) {
            return trim($candidate);
        }

        foreach ($definitions as $item) {
            if ($item['is_default'] && isset($bots[$item['code']])) {
                return $item['code'];
            }
        }

        if (isset($bots['account'])) {
            return 'account';
        }

        $first = array_key_first($bots);
        if (is_string($first) && $first !== '') {
            return $first;
        }

        $coreDefault = $this->coreRegistry->defaultName();
        if ($coreDefault !== '' && in_array($coreDefault, $this->coreRegistry->names(), true)) {
            return $coreDefault;
        }

        return 'account';
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

    private function resolveToken(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if ($this->tokenResolver === null) {
            return $token;
        }

        $resolved = ($this->tokenResolver)($token);
        if (!is_string($resolved)) {
            return null;
        }

        $resolved = trim($resolved);

        return $resolved !== '' ? $resolved : null;
    }
}
