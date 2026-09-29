<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use InvalidArgumentException;
use PhpSoftBox\MultiTenant\Provision\Runner\CommandLineSplitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandLineSplitter::class)]
#[CoversMethod(CommandLineSplitter::class, 'split')]
final class CommandLineSplitterTest extends TestCase
{
    /**
     * Проверим разбиение: пробелы разделяют аргументы, кавычки группируют и не попадают в аргумент, `\` экранирует.
     *
     * @see CommandLineSplitter::split()
     */
    #[Test]
    public function splitsQuotedArguments(): void
    {
        $this->assertSame(
            ['php', 'psb', 'cmd', '--name=Ivan Petrov', 'a b', '', 'say "hi"', 'x\'y', '$(id);'],
            CommandLineSplitter::split('  php psb cmd --name="Ivan Petrov" \'a b\' "" "say \"hi\"" x\\\'y $(id);  '),
        );
    }

    /**
     * Проверим, что незакрытая кавычка приводит к ошибке.
     *
     * @see CommandLineSplitter::split()
     */
    #[Test]
    public function unterminatedQuoteFails(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CommandLineSplitter::split('cmd --name="Ivan');
    }
}
