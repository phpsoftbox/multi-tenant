<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;

use function count;
use function is_array;

final readonly class TenantListHandler implements HandlerInterface
{
    public function __construct(
        private TenantProviderInterface $provider,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $showAll = $runner->request()->option('all', false) === true;
        $tenants = $this->provider->all(!$showAll);

        if ($tenants === []) {
            $runner->io()->writeln('Tenant не настроены.', 'warning');

            return Response::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $telegramBots = $tenant->data['telegram_bots'] ?? null;

            $runner->io()->writeln(
                $tenant->id
                . ' | connection=' . $tenant->databaseConnection
                . ' | db=' . ($tenant->databaseName ?? '-')
                . ' | primary_domain=' . ($tenant->primaryDomain ?? $tenant->host ?? '-')
                . ' | domains=' . count($tenant->domains)
                . ' | pushr_app_id=' . ($tenant->pushrAppId ?? '-')
                . ' | telegram_bots=' . (is_array($telegramBots) ? count($telegramBots) : 0)
                . ' | enabled=' . ($tenant->enabled ? '1' : '0'),
                'info',
            );
        }

        return Response::SUCCESS;
    }
}
