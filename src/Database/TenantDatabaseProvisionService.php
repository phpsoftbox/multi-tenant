<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Database;

use PDO;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Dsn\Dsn;
use PhpSoftBox\Database\Dsn\DsnParser;
use RuntimeException;

use function array_fill;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;

final readonly class TenantDatabaseProvisionService
{
    public function __construct(
        private ConnectionManagerInterface $connections,
        private Config $config,
    ) {
    }

    public function provision(
        string $templateConnection,
        string $targetConnection,
        string $migrationsTable = 'migrations',
        bool $dropExisting = false,
    ): void {
        if ($templateConnection === $targetConnection) {
            throw new RuntimeException('Template и target connection должны отличаться.');
        }

        $this->ensureTargetDatabaseExists($targetConnection);

        $template = $this->connections->write($templateConnection);
        $target   = $this->connections->write($targetConnection);

        $sourceDb = $this->currentDatabase($template);
        $targetDb = $this->currentDatabase($target);

        if ($sourceDb === '' || $targetDb === '') {
            throw new RuntimeException('Не удалось определить имя БД у template/target подключения.');
        }

        if ($sourceDb === $targetDb) {
            throw new RuntimeException('Template и target указывают на одну и ту же БД: ' . $sourceDb);
        }

        $sourceTables = $this->listTables($template);
        if ($sourceTables === []) {
            throw new RuntimeException('Template БД не содержит таблиц.');
        }

        $targetTables = $this->listTables($target);
        if ($targetTables !== [] && !$dropExisting) {
            throw new RuntimeException(
                'Target БД не пуста. Используйте --drop-existing, либо очистите БД вручную.',
            );
        }

        $target->execute('SET FOREIGN_KEY_CHECKS = 0');

        try {
            if ($dropExisting) {
                foreach ($targetTables as $table) {
                    $target->execute('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
                }
            }

            foreach ($sourceTables as $table) {
                $createSql = $this->showCreateTableSql($template, $table);
                $target->execute($createSql);
            }

            if (in_array($migrationsTable, $sourceTables, true)) {
                $this->copyTableData($template, $target, $migrationsTable, true);
            }
        } finally {
            $target->execute('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function ensureTargetDatabaseExists(string $targetConnection): void
    {
        $dsn = $this->resolveConnectionDsn($targetConnection);
        if (!in_array($dsn->driver, ['mariadb', 'mysql'], true)) {
            throw new RuntimeException(
                'tenant:db:provision сейчас поддерживает только mariadb/mysql. Получен driver: ' . $dsn->driver,
            );
        }

        if ($dsn->host === null || $dsn->host === '' || $dsn->database === null || $dsn->database === '') {
            throw new RuntimeException('Некорректный DSN для target connection: host/database обязательны.');
        }

        $port = $dsn->port ?? 3306;
        $user = $dsn->user ?? '';
        $pass = $dsn->password ?? '';

        $adminPdo = new PDO(
            sprintf('mysql:host=%s;port=%d', $dsn->host, $port),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        );

        $dbName = $this->quoteIdentifier($dsn->database);

        $charset   = $this->resolveSqlToken($dsn->params['charset'] ?? null, 'utf8mb4');
        $collation = $this->resolveSqlToken($dsn->params['collation'] ?? null, 'utf8mb4_unicode_ci');

        $adminPdo->exec(
            'CREATE DATABASE IF NOT EXISTS '
            . $dbName
            . ' CHARACTER SET '
            . $charset
            . ' COLLATE '
            . $collation,
        );
    }

    private function resolveConnectionDsn(string $connectionName): Dsn
    {
        $connections = $this->config->get('database.connections', []);
        if (!is_array($connections) || !isset($connections[$connectionName]) || !is_array($connections[$connectionName])) {
            throw new RuntimeException('Connection не найдена в config/database: ' . $connectionName);
        }

        $connectionConfig = $connections[$connectionName];
        $dsn              = null;

        if (isset($connectionConfig['write']) && is_array($connectionConfig['write'])) {
            $dsn = $connectionConfig['write']['dsn'] ?? null;
        }

        if (!is_string($dsn) || $dsn === '') {
            $dsn = $connectionConfig['dsn'] ?? null;
        }

        if (!is_string($dsn) || $dsn === '') {
            throw new RuntimeException('В connection "' . $connectionName . '" не найден DSN.');
        }

        return new DsnParser()->parse($dsn);
    }

    private function currentDatabase(ConnectionInterface $connection): string
    {
        $row = $connection->fetchOne('SELECT DATABASE() AS current_database');
        if (!is_array($row)) {
            return '';
        }

        $value = $row['current_database'] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    private function listTables(ConnectionInterface $connection): array
    {
        $rows = $connection->fetchAll('SHOW TABLES');
        $out  = [];
        foreach ($rows as $row) {
            $values = array_values($row);
            if (!isset($values[0]) || !is_string($values[0]) || $values[0] === '') {
                continue;
            }

            $out[] = $values[0];
        }

        return $out;
    }

    private function showCreateTableSql(ConnectionInterface $connection, string $table): string
    {
        $row = $connection->fetchOne('SHOW CREATE TABLE ' . $this->quoteIdentifier($table));
        if (!is_array($row)) {
            throw new RuntimeException('Не удалось получить DDL таблицы: ' . $table);
        }

        foreach ($row as $key => $value) {
            if (is_string($key) && preg_match('/^Create Table$/i', $key) === 1 && is_string($value) && $value !== '') {
                return $value;
            }
        }

        $values = array_values($row);
        if (isset($values[1]) && is_string($values[1]) && $values[1] !== '') {
            return $values[1];
        }

        throw new RuntimeException('Некорректный ответ SHOW CREATE TABLE для: ' . $table);
    }

    private function copyTableData(
        ConnectionInterface $source,
        ConnectionInterface $target,
        string $table,
        bool $truncateTarget = false,
    ): void {
        if ($truncateTarget) {
            $target->execute('DELETE FROM ' . $this->quoteIdentifier($table));
        }

        $rows = $source->fetchAll('SELECT * FROM ' . $this->quoteIdentifier($table));
        if ($rows === []) {
            return;
        }

        $columns = array_values(array_filter(
            array_keys($rows[0]),
            static fn (mixed $key): bool => is_string($key),
        ));
        if ($columns === []) {
            return;
        }

        $quotedColumns = array_map([$this, 'quoteIdentifier'], $columns);
        $placeholders  = array_fill(0, count($columns), '?');

        $sql = 'INSERT INTO '
            . $this->quoteIdentifier($table)
            . ' (' . implode(', ', $quotedColumns) . ') VALUES ('
            . implode(', ', $placeholders)
            . ')';

        foreach ($rows as $row) {
            $params = [];
            foreach ($columns as $column) {
                $params[] = $row[$column] ?? null;
            }

            $target->execute($sql, $params);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function resolveSqlToken(mixed $value, string $fallback): string
    {
        if (!is_string($value) || $value === '') {
            return $fallback;
        }

        if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
            throw new RuntimeException('Недопустимое значение SQL-токена: ' . $value);
        }

        return $value;
    }
}
