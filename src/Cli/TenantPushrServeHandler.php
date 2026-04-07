<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\Broadcaster\Pushr\PushrAppRegistry;
use PhpSoftBox\Broadcaster\Pushr\PushrServer;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Pushr\TenantPushrRegistrySource;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use RuntimeException;
use Throwable;

use function array_key_exists;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function trim;

final readonly class TenantPushrServeHandler implements HandlerInterface
{
    public function __construct(
        private TenantPushrRegistrySource $source,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $tenant = $runner->request()->option('tenant', 'all');
        if (!is_string($tenant) || $tenant === '') {
            $runner->io()->writeln('Некорректный параметр --tenant.', 'error');

            return Response::FAILURE;
        }

        $host = $runner->request()->option('host', '0.0.0.0');
        if (!is_string($host) || $host === '') {
            $runner->io()->writeln('Некорректный параметр --host.', 'error');

            return Response::FAILURE;
        }

        $port = $runner->request()->option('port', 8080);
        if (!is_int($port) || $port < 1) {
            $runner->io()->writeln('Некорректный параметр --port.', 'error');

            return Response::FAILURE;
        }

        $maxSkew = $runner->request()->option('max-skew', 300);
        if (!is_int($maxSkew) || $maxSkew < 0) {
            $runner->io()->writeln('Некорректный параметр --max-skew.', 'error');

            return Response::FAILURE;
        }

        try {
            $apps = $this->source->apps(['tenant' => $tenant]);
            if ($apps === []) {
                throw new RuntimeException('Не найдено ни одного tenant-приложения для Pushr.');
            }

            $registry = new PushrAppRegistry($apps);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln(
            'Pushr server: host=' . $host . ', port=' . $port . ', apps=' . count($registry->all()),
            'success',
        );

        $server = new PushrServer($registry, $host, $port, $maxSkew);

        $server->run();

        return Response::SUCCESS;
    }

    /**
     * @param list<TenantDefinition> $tenants
     */
    private function buildRegistry(array $tenants): PushrAppRegistry
    {
        $apps = [];

        foreach ($tenants as $tenant) {
            [$appId, $secret] = $this->resolvePushrCredentials($tenant);

            if ($appId === null || $secret === null) {
                throw new RuntimeException(
                    'У tenant "' . $tenant->id . '" не настроены pushr_app_id/pushr_secret.',
                );
            }

            if (isset($apps[$appId]) && $apps[$appId] !== $secret) {
                throw new RuntimeException(
                    'Конфликт Pushr app_id "' . $appId . '" у tenant "' . $tenant->id . '".',
                );
            }

            $apps[$appId] = $secret;
        }

        if ($apps === []) {
            throw new RuntimeException('Не найдено ни одного tenant-приложения для Pushr.');
        }

        return new PushrAppRegistry($apps);
    }

    /**
     * @return array{0:?string, 1:?string}
     */
    private function resolvePushrCredentials(TenantDefinition $tenant): array
    {
        $appId = $this->normalizeString($tenant->pushrAppId)
            ?? $this->dataString($tenant->data, 'pushr', 'app_id')
            ?? $this->dataString($tenant->data, 'pushr_app_id');

        $secret = $this->normalizeString($tenant->pushrSecret)
            ?? $this->dataString($tenant->data, 'pushr', 'secret')
            ?? $this->dataString($tenant->data, 'pushr_secret');

        return [$appId, $secret];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function dataString(array $data, string ...$path): ?string
    {
        if ($path === []) {
            return null;
        }

        $cursor = $data;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $this->normalizeString($cursor);
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
