<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsInterface;
use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsResolverInterface;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use Throwable;

use function in_array;
use function is_int;
use function is_string;
use function trim;

abstract readonly class AbstractTenantAuthRoleHandler implements HandlerInterface
{
    public function __construct(
        private ScopedAuthRoleOperationsResolverInterface $operations,
        private TenantSelector $selector,
        private TenantRuntimeExecutor $runtime,
    ) {
    }

    protected function scope(RunnerInterface $runner, string $default): ?string
    {
        $scope = $runner->request()->option('scope', $default);
        if (!is_string($scope)) {
            $runner->io()->writeln('Параметр --scope должен быть строкой.', 'error');

            return null;
        }

        $scope = trim($scope);
        if (!in_array($scope, ['all', 'core', 'tenant'], true)) {
            $runner->io()->writeln('Допустимые значения --scope: all|core|tenant.', 'error');

            return null;
        }

        return $scope;
    }

    protected function tenantOption(RunnerInterface $runner, ?string $default): ?string
    {
        $tenant = $runner->request()->option('tenant', $default);
        if ($tenant === null) {
            return null;
        }

        if (!is_string($tenant)) {
            $runner->io()->writeln('Параметр --tenant должен быть строкой.', 'error');

            return null;
        }

        $tenant = trim($tenant);

        return $tenant !== '' ? $tenant : null;
    }

    protected function user(RunnerInterface $runner): int|string|null
    {
        $user = $runner->request()->param('user', '');
        if (is_int($user)) {
            return $user > 0 ? $user : null;
        }

        if (!is_string($user)) {
            return null;
        }

        $user = trim($user);

        return $user !== '' ? $user : null;
    }

    protected function role(RunnerInterface $runner): string
    {
        $role = $runner->request()->param('role', '');

        return is_string($role) ? trim($role) : '';
    }

    /**
     * @param callable(ScopedAuthRoleOperationsInterface, string): void $operation
     */
    protected function runForScope(
        RunnerInterface $runner,
        string $scope,
        ?string $tenantOption,
        callable $operation,
        bool $requireExplicitTenant = false,
    ): bool {
        if (($scope === 'all' || $scope === 'tenant') && $tenantOption === null) {
            $message = $requireExplicitTenant
                ? 'Укажите --tenant=<id> или --tenant=all для tenant-операции.'
                : 'Параметр --tenant должен быть непустой строкой.';

            $runner->io()->writeln($message, 'error');

            return false;
        }

        $allOk = true;

        if ($scope === 'all' || $scope === 'core') {
            $allOk = $this->execute($runner, 'core', fn (): mixed => $operation($this->operations->core(), 'core')) && $allOk;
        }

        if ($scope === 'all' || $scope === 'tenant') {
            $allOk = $this->runForTenants($runner, (string) $tenantOption, $operation) && $allOk;
        }

        return $allOk;
    }

    /**
     * @param callable(ScopedAuthRoleOperationsInterface, string): void $operation
     */
    private function runForTenants(RunnerInterface $runner, string $tenantOption, callable $operation): bool
    {
        try {
            $tenants = $this->selector->select($tenantOption, true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return false;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для auth role операции.', 'warning');

            return true;
        }

        $allOk = true;
        foreach ($tenants as $tenant) {
            $label = 'tenant:' . $tenant->id;

            try {
                $ok = $this->runtime->run(
                    $tenant,
                    fn (): bool => $this->execute(
                        $runner,
                        $label,
                        fn (): mixed => $operation($this->operations->tenant(), $label),
                    ),
                );
            } catch (Throwable $exception) {
                $runner->io()->writeln('[' . $label . '] ' . $exception->getMessage(), 'error');
                $allOk = false;
                continue;
            }

            if (!$ok) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    private function execute(RunnerInterface $runner, string $scopeLabel, callable $operation): bool
    {
        try {
            $operation();

            return true;
        } catch (Throwable $exception) {
            $runner->io()->writeln('[' . $scopeLabel . '] ' . $exception->getMessage(), 'error');

            return false;
        }
    }
}
