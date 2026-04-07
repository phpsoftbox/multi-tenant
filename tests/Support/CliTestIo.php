<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\CliApp\Io\IoInterface;
use PhpSoftBox\CliApp\Io\ProgressInterface;

final class CliTestIo implements IoInterface
{
    /**
     * @var list<string>
     */
    public array $messages = [];

    public function ask(string $question, ?string $default = null): string
    {
        return $default ?? '';
    }

    public function confirm(string $question, bool $default = false): bool
    {
        return $default;
    }

    public function secret(string $question): string
    {
        return '';
    }

    public function writeln(string $message, string $style = 'info'): void
    {
        $this->messages[] = '[' . $style . '] ' . $message;
    }

    public function table(array $headers, array $rows): void
    {
    }

    public function progress(int $max): ProgressInterface
    {
        return new class () implements ProgressInterface {
            public function advance(int $step = 1): void
            {
            }

            public function finish(): void
            {
            }
        };
    }
}
