<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision\Runner;

use InvalidArgumentException;

use function ctype_space;
use function strlen;

/**
 * Разбивает строку команды на аргументы без участия shell: пробельные символы разделяют аргументы, `'...'` и `"..."`
 * группируют (кавычки в аргумент не попадают), внутри `"..."` и вне кавычек `\` экранирует следующий символ.
 * Подстановки, переменные окружения и операторы shell (`$()`, `;`, `|`, `>`) не интерпретируются.
 */
final class CommandLineSplitter
{
    /**
     * @return list<string>
     */
    public static function split(string $command): array
    {
        $arguments = [];
        $current   = '';
        $started   = false;
        $quote     = null;
        $length    = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            if ($quote === "'") {
                if ($char === "'") {
                    $quote = null;
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($char === '\\' && $i + 1 < $length && ($quote === null || $command[$i + 1] === '"' || $command[$i + 1] === '\\')) {
                $current .= $command[++$i];
                $started = true;

                continue;
            }

            if ($quote === '"') {
                if ($char === '"') {
                    $quote = null;
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote   = $char;
                $started = true;

                continue;
            }

            if (ctype_space($char)) {
                if ($started) {
                    $arguments[] = $current;
                    $current     = '';
                    $started     = false;
                }

                continue;
            }

            $current .= $char;
            $started = true;
        }

        if ($quote !== null) {
            throw new InvalidArgumentException('Незакрытая кавычка в команде: ' . $command);
        }

        if ($started) {
            $arguments[] = $current;
        }

        return $arguments;
    }
}
