<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Runner;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionCommandRunnerInterface;
use RuntimeException;

use function fclose;
use function is_resource;
use function is_string;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function trim;

use const PHP_EOL;

final readonly class ShellTenantProvisionCommandRunner implements TenantProvisionCommandRunnerInterface
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function run(string $command): void
    {
        $command = trim($command);
        if ($command === '') {
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

        $shellCommand = trim($binary) . ' ' . $command;
        $descriptors  = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($shellCommand, $descriptors, $pipes, $workingDirectory);
        if (!is_resource($process)) {
            throw new RuntimeException('Не удалось запустить команду provision: ' . $shellCommand);
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $output  = trim($stderr !== '' ? $stderr : $stdout);
            $message = 'Provision command failed (' . $exitCode . '): ' . $shellCommand;
            if ($output !== '') {
                $message .= PHP_EOL . $output;
            }

            throw new RuntimeException($message);
        }
    }
}
