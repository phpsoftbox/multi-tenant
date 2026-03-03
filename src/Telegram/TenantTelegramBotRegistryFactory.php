<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Telegram;

use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Bot\TelegramBot;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function array_key_first;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function trim;

final readonly class TenantTelegramBotRegistryFactory
{
    public function __construct(
        private TelegramBotRegistry $coreRegistry,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function create(TenantDefinition $tenant): TelegramBotRegistry
    {
        $definitions = $this->normalizeBots($tenant->data);

        $bots = [];
        foreach ($definitions as $item) {
            $code  = $item['code'];
            $token = $item['token'];

            $handler = $this->coreRegistry->bot($code)?->handler() ?? $this->coreRegistry->handler();
            $client  = new TelegramClient(
                token: $token,
                httpClient: $this->httpClient,
                requestFactory: $this->requestFactory,
                streamFactory: $this->streamFactory,
            );

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

    /**
     * @param array<string, mixed> $tenantData
     *
     * @return list<array{code:string,token:string,is_default:bool}>
     */
    private function normalizeBots(array $tenantData): array
    {
        $botsRaw = $tenantData['telegram_bots'] ?? null;
        if (!is_array($botsRaw) && isset($tenantData['telegram']) && is_array($tenantData['telegram'])) {
            $botsRaw = $tenantData['telegram']['bots'] ?? null;
        }

        if (!is_array($botsRaw)) {
            return [];
        }

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

            $token = $item['token'] ?? null;
            if (!is_string($token) || trim($token) === '') {
                continue;
            }

            $code = trim($code);

            $out[$code] = [
                'code'       => $code,
                'token'      => trim($token),
                'is_default' => (bool) ($item['is_default'] ?? false),
            ];
        }

        return array_values($out);
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
}
