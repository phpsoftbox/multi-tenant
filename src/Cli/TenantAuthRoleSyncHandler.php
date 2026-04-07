<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\Auth\Authorization\RoleSyncPlan;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsInterface;

use function count;
use function implode;

final readonly class TenantAuthRoleSyncHandler extends AbstractTenantAuthRoleHandler
{
    public function run(RunnerInterface $runner): int|Response
    {
        $scope  = $this->scope($runner, 'all');
        $tenant = $this->tenantOption($runner, 'all');
        $dryRun = $runner->request()->option('dry-run', false) === true;

        if ($scope === null) {
            return Response::FAILURE;
        }

        $ok = $this->runForScope(
            runner: $runner,
            scope: $scope,
            tenantOption: $tenant,
            operation: function (ScopedAuthRoleOperationsInterface $operations, string $label) use ($runner, $dryRun): void {
                if ($dryRun) {
                    $this->printPlan($runner, $label, $operations->sync(true));

                    return;
                }

                $operations->sync(false);
                $runner->io()->writeln('[' . $label . '] roles synced.', 'success');
            },
        );

        return $ok ? Response::SUCCESS : Response::FAILURE;
    }

    private function printPlan(RunnerInterface $runner, string $label, ?RoleSyncPlan $plan): void
    {
        if (!$plan instanceof RoleSyncPlan) {
            $runner->io()->writeln('[' . $label . '] dry-run completed.', 'info');

            return;
        }

        $runner->io()->writeln(
            '[' . $label . '] dry-run: +roles=' . count($plan->rolesToCreate)
            . ', -roles=' . count($plan->rolesToDelete)
            . ', +permissions=' . count($plan->permissionsToCreate)
            . ', -permissions=' . count($plan->permissionsToDelete),
            'info',
        );

        if ($plan->rolesToCreate !== []) {
            $runner->io()->writeln('[' . $label . '] create roles: ' . implode(', ', $plan->rolesToCreate), 'info');
        }
        if ($plan->rolesToDelete !== []) {
            $runner->io()->writeln('[' . $label . '] delete roles: ' . implode(', ', $plan->rolesToDelete), 'warning');
        }
        if ($plan->permissionsToCreate !== []) {
            $runner->io()->writeln('[' . $label . '] create permissions: ' . implode(', ', $plan->permissionsToCreate), 'info');
        }
        if ($plan->permissionsToDelete !== []) {
            $runner->io()->writeln('[' . $label . '] delete permissions: ' . implode(', ', $plan->permissionsToDelete), 'warning');
        }
    }
}
