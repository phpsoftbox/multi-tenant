<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Provision\Runner\ShellTenantProvisionCommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use const PHP_BINARY;

#[CoversClass(ShellTenantProvisionCommandRunner::class)]
#[CoversMethod(ShellTenantProvisionCommandRunner::class, 'run')]
final class ShellTenantProvisionCommandRunnerTest extends TestCase
{
    /**
     * Проверим, что аргумент с операторами shell доходит до процесса как есть: процесс запускается без shell.
     *
     * @see ShellTenantProvisionCommandRunner::run()
     */
    #[Test]
    public function argumentsArePassedWithoutShell(): void
    {
        $runner = new ShellTenantProvisionCommandRunner($this->config());

        // Скрипт завершается с 0, только если получил аргумент без изменений.
        $runner->run(['-r', 'exit($argv[1] === "x; exit 7 $(id)" ? 0 : 3);', 'x; exit 7 $(id)']);

        $this->addToAssertionCount(1);
    }

    /**
     * Проверим, что ненулевой код выхода превращается в исключение с выводом процесса (stdout и stderr).
     *
     * @see ShellTenantProvisionCommandRunner::run()
     */
    #[Test]
    public function failedCommandThrowsWithOutput(): void
    {
        $runner = new ShellTenantProvisionCommandRunner($this->config());

        try {
            $runner->run(['-r', 'fwrite(STDERR, "boom"); exit(4);']);
            $this->fail('Ожидалось исключение.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('(4)', $exception->getMessage());
            $this->assertStringContainsString('boom', $exception->getMessage());
        }
    }

    private function config(): Config
    {
        return new Config([[
            'tenancy' => ['provision' => ['command_runner' => ['binary' => PHP_BINARY]]],
        ]]);
    }
}
