<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsInterface;

final readonly class TenantAuthRoleAssignHandler extends AbstractTenantAuthRoleHandler
{
    public function run(RunnerInterface $runner): int|Response
    {
        $scope  = $this->scope($runner, 'tenant');
        $tenant = $this->tenantOption($runner, null);
        $user   = $this->user($runner);
        $role   = $this->role($runner);

        if ($scope === null) {
            return Response::FAILURE;
        }

        if ($user === null) {
            $runner->io()->writeln('Укажите ID пользователя.', 'error');

            return Response::FAILURE;
        }

        if ($role === '') {
            $runner->io()->writeln('Укажите имя роли.', 'error');

            return Response::FAILURE;
        }

        $ok = $this->runForScope(
            runner: $runner,
            scope: $scope,
            tenantOption: $tenant,
            operation: static function (ScopedAuthRoleOperationsInterface $operations, string $label) use ($runner, $user, $role): void {
                $operations->assignRole($user, $role);
                $runner->io()->writeln('[' . $label . '] Роль ' . $role . ' назначена пользователю ' . $user . '.', 'success');
            },
            requireExplicitTenant: true,
        );

        return $ok ? Response::SUCCESS : Response::FAILURE;
    }
}
