<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Repository\Auth;

use PhpSoftBox\Database\Contracts\ConnectionInterface;

use function is_array;

final readonly class UserRoleRepository
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {
    }

    public function assign(int $userId, int $roleId): void
    {
        $table = 'user_roles';

        $sql = 'SELECT user_id FROM ' . $this->connection->table($table)
            . ' WHERE user_id = :user_id AND role_id = :role_id LIMIT 1';

        $existing = $this->connection->fetchOne($sql, [
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        if (is_array($existing)) {
            return;
        }

        $this->connection->query()->insert($table, [
            'user_id' => $userId,
            'role_id' => $roleId,
        ])->execute();
    }
}
