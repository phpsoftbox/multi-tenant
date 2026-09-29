<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Contracts;

interface TenantProvisionCommandRunnerInterface
{
    /**
     * Запускает CLI-команду provisioning. Аргументы передаются процессу как есть, без shell: значения из payload
     * (имя, e-mail владельца) не могут внедрить команды.
     *
     * @param list<string> $arguments аргументы команды (без бинарника), например `['tenant:auth:role:sync', '--tenant=5']`
     */
    public function run(array $arguments): void;
}
