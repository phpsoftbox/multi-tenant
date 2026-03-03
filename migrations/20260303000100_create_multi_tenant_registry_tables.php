<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class extends AbstractMigration
{
    public function up(): void
    {
        $this->schema()->create('tenants', function (TableBlueprint $table): void {
            $table->id()->comment('PK');
            $table->foreignId('user_id')->nullable()->comment('Владелец tenant (core users.id)');
            $table->string('tenant_id', 100)->comment('Публичный tenant ID');
            $table->string('name', 255)->comment('Название tenant');
            $table->string('database_connection', 100)->comment('Имя подключения БД');
            $table->string('database_name', 255)->nullable()->comment('Имя tenant БД');
            $table->boolean('enabled')->default(true)->comment('Активен ли tenant');
            $table->json('data')->nullable()->comment('JSON payload с расширенными настройками tenant');
            $table->datetime('created_datetime')->useCurrent()->comment('Время создания');
            $table->datetime('updated_datetime')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');
            $table->unique(['tenant_id'], 'mt_tenants_tenant_id_unique');
            $table->index(['enabled'], 'mt_tenants_enabled_idx');
            $table->index(['user_id'], 'mt_tenants_user_id_idx');
            $table->engine('InnoDB');
        });

        $this->schema()->create('domains', function (TableBlueprint $table): void {
            $table->id()->comment('PK');
            $table->string('tenant_id', 100)->comment('Tenant ID');
            $table->string('domain', 255)->comment('Домен tenant');
            $table->boolean('is_primary')->default(false)->comment('Главный домен tenant');
            $table->boolean('enabled')->default(true)->comment('Активен ли домен');
            $table->json('data')->nullable()->comment('JSON payload для домена');
            $table->datetime('created_datetime')->useCurrent()->comment('Время создания');
            $table->datetime('updated_datetime')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');
            $table->unique(['domain'], 'mt_domains_domain_unique');
            $table->index(['tenant_id', 'enabled'], 'mt_domains_tenant_enabled_idx');
            $table->foreignKey(['tenant_id'], 'tenants', ['tenant_id'])
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->engine('InnoDB');
        });

    }

    public function down(): void
    {
        $this->schema()->dropIfExists('domains');
        $this->schema()->dropIfExists('tenants');
    }
};
