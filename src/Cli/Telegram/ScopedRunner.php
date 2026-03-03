<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli\Telegram;

use PhpSoftBox\CliApp\Io\IoInterface;
use PhpSoftBox\CliApp\Request\Request;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;

final readonly class ScopedRunner implements RunnerInterface
{
    public function __construct(
        private RunnerInterface $inner,
        private Request $request,
    ) {
    }

    public function run(string $command, array $argv): Response
    {
        return $this->inner->run($command, $argv);
    }

    public function runSubCommand(string $command, array $argv): Response
    {
        return $this->inner->runSubCommand($command, $argv);
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function io(): IoInterface
    {
        return $this->inner->io();
    }
}
