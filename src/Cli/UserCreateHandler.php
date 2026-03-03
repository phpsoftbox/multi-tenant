<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\Auth\Credentials\PasswordHasherInterface;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Entity\Auth\Role;
use PhpSoftBox\MultiTenant\Repository\Auth\RoleRepository;
use PhpSoftBox\MultiTenant\Repository\Auth\UserRepository;
use PhpSoftBox\MultiTenant\Repository\Auth\UserRoleRepository;
use PhpSoftBox\MultiTenant\Tenant\Runtime\TenantRuntimeExecutor;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Validator\Filter\PhoneFilter;
use Throwable;

use function bin2hex;
use function ctype_digit;
use function in_array;
use function is_int;
use function is_string;
use function random_bytes;
use function trim;

final readonly class UserCreateHandler implements HandlerInterface
{
    public function __construct(
        private ConnectionManagerInterface $connections,
        private PasswordHasherInterface $hasher,
        private Config $config,
        private TenantSelector $selector,
        private TenantRuntimeExecutor $runtime,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $phone    = (new PhoneFilter())((string) $runner->request()->param('phone'));
        $password = (string) $runner->request()->param('password');
        $email    = $runner->request()->option('email');
        $name     = (string) $runner->request()->option('name', 'Администратор');
        $role     = $runner->request()->option('role');
        $scope    = $runner->request()->option('scope', 'core');

        if (!is_string($phone) || $phone === '' || trim($password) === '') {
            $runner->io()->writeln('Телефон и пароль обязательны.', 'error');

            return Response::FAILURE;
        }

        if (!is_string($scope) || !in_array(trim($scope), ['core', 'tenant'], true)) {
            $runner->io()->writeln('Параметр --scope должен быть одним из: core|tenant.', 'error');

            return Response::FAILURE;
        }

        $scope = trim($scope);
        $email = is_string($email) && trim($email) !== '' ? trim($email) : null;
        $name  = trim($name) !== '' ? trim($name) : 'Администратор';

        $roleValue = is_string($role) && trim($role) !== ''
            ? trim($role)
            : (string) $this->config->get('auth.roles.admin', 'admin');

        if ($scope === 'core') {
            $connectionName = (string) $this->config->get('auth.providers.users.connection', 'default');

            return $this->createOnConnection(
                runner: $runner,
                connectionName: $connectionName,
                phone: $phone,
                password: $password,
                email: $email,
                name: $name,
                roleValue: $roleValue,
                label: 'core',
            );
        }

        $tenantOption = $runner->request()->option('tenant', 'all');
        if (!is_string($tenantOption) || trim($tenantOption) === '') {
            $runner->io()->writeln('Для scope=tenant параметр --tenant обязателен (id|all).', 'error');

            return Response::FAILURE;
        }

        try {
            $tenants = $this->selector->select(trim($tenantOption), true);
        } catch (Throwable $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($tenants === []) {
            $runner->io()->writeln('Не найдено tenant для создания пользователя.', 'warning');

            return Response::SUCCESS;
        }

        $tenantConnection = (string) $this->config->get('tenancy.runtime.database.connection_alias', 'tenant');
        $errors           = 0;

        foreach ($tenants as $tenant) {
            try {
                $result = $this->runtime->run(
                    $tenant,
                    fn (): int => $this->createOnConnection(
                        runner: $runner,
                        connectionName: $tenantConnection,
                        phone: $phone,
                        password: $password,
                        email: $email,
                        name: $name,
                        roleValue: $roleValue,
                        label: 'tenant:' . $tenant->id,
                    ),
                );
            } catch (Throwable $exception) {
                $runner->io()->writeln('[tenant:' . $tenant->id . '] ' . $exception->getMessage(), 'error');
                $errors++;
                continue;
            }

            if ((int) $result !== Response::SUCCESS) {
                $errors++;
            }
        }

        return $errors === 0 ? Response::SUCCESS : Response::FAILURE;
    }

    private function createOnConnection(
        RunnerInterface $runner,
        string $connectionName,
        string $phone,
        string $password,
        ?string $email,
        string $name,
        string $roleValue,
        string $label,
    ): int {
        $em = new EntityManager($this->connections->write($connectionName));

        $users     = new UserRepository($em);
        $roles     = new RoleRepository($em);
        $userRoles = new UserRoleRepository($em->connection());

        if ($users->findByPhone($phone) !== null) {
            $runner->io()->writeln('[' . $label . '] пользователь с таким телефоном уже существует.', 'error');

            return Response::FAILURE;
        }

        if ($email !== null && $users->findByEmail($email) !== null) {
            $runner->io()->writeln('[' . $label . '] пользователь с таким email уже существует.', 'error');

            return Response::FAILURE;
        }

        $role = $this->resolveRole($roles, $roleValue);
        if ($role === null || !is_int($role->id)) {
            $runner->io()->writeln('[' . $label . '] роль не найдена: ' . $roleValue, 'error');

            return Response::FAILURE;
        }

        try {
            $passwordHash = $this->hasher->hash($password);
            $authToken    = bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            $runner->io()->writeln('[' . $label . '] ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        try {
            $user = $users->create(
                name: $name,
                phone: $phone,
                email: $email,
                passwordHash: $passwordHash,
                authToken: $authToken,
                status: 'active',
            );

            if (!is_int($user->id)) {
                $runner->io()->writeln('[' . $label . '] не удалось определить ID созданного пользователя.', 'error');

                return Response::FAILURE;
            }

            $userRoles->assign($user->id, $role->id);
        } catch (Throwable $exception) {
            $runner->io()->writeln('[' . $label . '] ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('[' . $label . '] пользователь создан: #' . $user->id, 'success');

        return Response::SUCCESS;
    }

    private function resolveRole(RoleRepository $roles, string $roleValue): ?Role
    {
        if ($roleValue !== '' && ctype_digit($roleValue)) {
            $resolved = $roles->findById((int) $roleValue);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $roles->findByName($roleValue);
    }
}
