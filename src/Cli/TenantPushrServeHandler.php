<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\Broadcaster\Pushr\PushrAppRegistry;
use PhpSoftBox\Broadcaster\Pushr\PushrServer;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Pushr\TenantPushrRegistrySource;
use RuntimeException;
use Throwable;

use function count;
use function is_int;
use function is_string;

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
}
