<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Runner;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionCommandRunnerInterface;
use RuntimeException;

use function array_map;
use function array_merge;
use function escapeshellarg;
use function fclose;
use function implode;
use function is_resource;
use function is_string;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function trim;

use const PHP_EOL;

/**
 * Запускает команды provisioning отдельным процессом: `tenancy.provision.command_runner.binary` (по умолчанию
 * `php psb`) + аргументы команды. Процесс запускается без shell (`proc_open` со списком аргументов).
 */
final readonly class ShellTenantProvisionCommandRunner implements TenantProvisionCommandRunnerInterface
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function run(array $arguments): void
    {
        if ($arguments === []) {
            return;
        }

        $binary = $this->config->get('tenancy.provision.command_runner.binary', 'php psb');
        if (!is_string($binary) || trim($binary) === '') {
            throw new RuntimeException('tenancy.provision.command_runner.binary должен быть непустой строкой.');
        }

        $workingDirectory = $this->config->get('tenancy.provision.command_runner.cwd', null);
        if (!is_string($workingDirectory) || trim($workingDirectory) === '') {
            $workingDirectory = null;
        }

        $command     = array_merge(CommandLineSplitter::split($binary), $arguments);
        $commandLine = implode(' ', array_map(escapeshellarg(...), $command));
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['redirect', 1],
        ];

        $process = proc_open($command, $descriptors, $pipes, $workingDirectory);
        if (!is_resource($process)) {
            throw new RuntimeException('Не удалось запустить команду provision: ' . $commandLine);
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $message = 'Provision command failed (' . $exitCode . '): ' . $commandLine;
            $output  = trim($output);
            if ($output !== '') {
                $message .= PHP_EOL . $output;
            }

            throw new RuntimeException($message);
        }
    }
}
