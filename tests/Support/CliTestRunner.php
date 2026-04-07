<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\CliApp\Io\IoInterface;
use PhpSoftBox\CliApp\Request\Request;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;

use function str_contains;

final class CliTestRunner implements RunnerInterface
{
    private CliTestIo $io;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $params = [],
        private readonly array $options = [],
    ) {
        $this->io = new CliTestIo();
    }

    public function run(string $command, array $argv): Response
    {
        return new Response(Response::SUCCESS);
    }

    public function runSubCommand(string $command, array $argv): Response
    {
        return new Response(Response::SUCCESS);
    }

    public function request(): Request
    {
        return new Request($this->params, $this->options);
    }

    public function io(): IoInterface
    {
        return $this->io;
    }

    public function containsMessage(string $needle): bool
    {
        foreach ($this->io->messages as $message) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
