<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class extends AbstractMigration
{
    public function up(): void
    {
        $this->schema()->create('telegram_bots', function (TableBlueprint $table): void {
            $table->id()->comment('Идентификатор бота');
            $table->foreignId('tenant_id')->comment('Идентификатор tenant');
            $table->string('code', 100)->comment('Код бота');
            $table->string('token', 255)->comment('Токен бота');
            $table->string('username', 255)->nullable()->comment('Имя пользователя Telegram');
            $table->boolean('is_default')->default(false)->comment('Бот по умолчанию для tenant');
            $table->boolean('is_enabled')->default(true)->comment('Активен ли бот');
            $table->json('data')->nullable()->comment('JSON payload с дополнительными настройками');
            $table->datetime('created_datetime')->useCurrent()->comment('Время создания');
            $table->datetime('updated_datetime')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            $table->unique(['tenant_id', 'code'], 'mt_telegram_bots_tenant_code_unique');
            $table->index(['tenant_id', 'is_enabled'], 'mt_telegram_bots_tenant_is_enabled_idx');
            $table->index(['tenant_id', 'is_default'], 'mt_telegram_bots_tenant_default_idx');

            $table->foreignKey(['tenant_id'], 'tenants', ['id'])
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
