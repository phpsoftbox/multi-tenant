<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class extends AbstractMigration
{
    public function up(): void
    {
        $this->schema()->create('tenants', function (TableBlueprint $table): void {
            $table->id()->comment('Идентификатор площадки');
            $table->foreignId('user_id')->nullable()->comment('Идентификатор владельца площадки');
            $table->string('name', 255)->comment('Название площадки');
            $table->string('database_connection', 100)->comment('Имя подключения БД');
            $table->string('database_name', 255)->nullable()->comment('Имя tenant БД');
            $table->boolean('is_enabled')->default(true)->comment('Активна ли площадка');
            $table->json('data')->nullable()->comment('JSON payload с расширенными настройками tenant');
            $table->datetime('created_datetime')->useCurrent()->comment('Время создания');
            $table->datetime('updated_datetime')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            $table->index(['is_enabled'], 'mt_tenants_is_enabled_idx');
            $table->index(['user_id'], 'mt_tenants_user_id_idx');

            $table->engine('InnoDB');

            $table->comment('Площадки');
        });

        $this->schema()->create('domains', function (TableBlueprint $table): void {
            $table->id()->comment('Идентификатор домена');
            $table->foreignId('tenant_id')->comment('Идентификатор площадки');
            $table->string('domain', 255)->comment('Домен tenant');
            $table->boolean('is_primary')->default(false)->comment('Главный домен площадки');
            $table->boolean('is_enabled')->default(true)->comment('Активен ли домен');
            $table->json('data')->nullable()->comment('JSON payload для домена');
            $table->datetime('created_datetime')->useCurrent()->comment('Время создания');
            $table->datetime('updated_datetime')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            $table->unique(['domain'], 'mt_domains_domain_unique');
            $table->index(['tenant_id', 'is_enabled'], 'mt_domains_tenant_is_enabled_idx');

            $table->foreignKey(['tenant_id'], 'tenants', ['id'])
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->engine('InnoDB');

            $table->comment('Домены площадок');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('domains');
        $this->schema()->dropIfExists('tenants');
    }
};
