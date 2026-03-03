<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class extends AbstractMigration
{
    public function up(): void
    {
        $this->schema()->create('telegram_bots', function (TableBlueprint $table): void {
            $table->id()->comment('PK');
            $table->string('tenant_id', 100)->comment('Tenant ID');
            $table->string('code', 100)->comment('Bot code');
            $table->string('token', 255)->comment('Bot token');
            $table->string('username', 255)->nullable()->comment('Telegram username');
            $table->boolean('is_default')->default(false)->comment('Default bot for tenant');
            $table->boolean('enabled')->default(true)->comment('Enabled flag');
            $table->json('data')->nullable()->comment('JSON payload');
            $table->datetime('created_datetime')->useCurrent()->comment('Время создания');
            $table->datetime('updated_datetime')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');
            $table->unique(['tenant_id', 'code'], 'mt_telegram_bots_tenant_code_unique');
            $table->index(['tenant_id', 'enabled'], 'mt_telegram_bots_tenant_enabled_idx');
            $table->index(['tenant_id', 'is_default'], 'mt_telegram_bots_tenant_default_idx');
            $table->foreignKey(['tenant_id'], 'tenants', ['tenant_id'])
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->engine('InnoDB');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('telegram_bots');
    }
};
