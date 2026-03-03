# PhpSoftBox MultiTenant

`phpsoftbox/multi-tenant` — расширение для multi-tenant сценариев:

- выбор tenant в CLI (`--tenant`, по умолчанию `all`)
- tenant-aware миграции (`tenant:db:migrate`, `tenant:db:rollback`)
- provisioning tenant БД из template (`tenant:db:provision`)
- tenant-aware запуск Pushr (`tenant:pushr:serve`) с `PushrAppRegistry` по tenant-приложениям
- два провайдера tenant-реестра:
  - `ConfigTenantProvider`
  - `DatabaseTenantProvider` (core БД, JSON payload `data` через ORM typecaster)

Рекомендуемая production-схема: `DatabaseTenantProvider` как primary, `ConfigTenantProvider` использовать только как fallback/parity-check.

## Команды

- `tenant:list`
- `tenant:config:check [--primary=database|config] [--connection=default]`
- `tenant:db:migrate [--tenant=all] [--path=...] [--fail-fast]`
- `tenant:db:rollback [--tenant=all] [--path=...] [--steps=1] [--fail-fast]`
- `tenant:db:provision [--tenant=all] [--template=<id>] [--migrations-table=migrations] [--drop-existing] [--fail-fast]`
- `tenant:pushr:serve [--tenant=all] [--host=0.0.0.0] [--port=8080] [--max-skew=300]`
- `tenant:queue:core:run [--max-jobs=0]`
- `tenant:queue:tenant:run [--tenant=all] [--max-jobs=0]`

## Конфиг проекта (file provider)

```php
// config/app/tenancy.php
return [
    'providers' => [
        'primary' => 'database', // config|database
        'enforce_parity' => true,
        'database' => [
            'connection' => 'default',
        ],
    ],
    'tenants' => [
        'tenant-1' => [
            'name' => 'Tenant 1',
            'database_connection' => 'tenant',
            'database_name' => 'tenant_db_1',
            'domains' => [
                ['domain' => 'tenant1.chegdesklad.local', 'is_primary' => true],
                ['domain' => 'www.tenant1.chegdesklad.local'],
            ],
            'data' => [
                'pushr' => [
                    'app_id' => 'tenant-1',
                    'secret' => 'secret-tenant-1',
                ],
                'telegram_bots' => [
                    [
                        'code' => 'account',
                        'token' => 'telegram-token-account',
                        'username' => 'account_bot',
                        'is_default' => true,
                        'enabled' => true,
                    ],
                ],
            ],
            'enabled' => true,
        ],
    ],
    'provision' => [
        'template_tenant' => 'tenant-template',
    ],
];
```

## Database provider

`DatabaseTenantProvider` читает core-таблицы:

- `tenants`
- `domains`

`tenants.user_id` (nullable) можно использовать для связи tenant с владельцем из core DB
(FK при необходимости добавляется проектной миграцией).

JSON поля `data` в ORM Entity маппятся через `#[Column(type: 'json')]` и `DefaultTypeCasterFactory`.
В `data` хранятся tenant-настройки (pushr, fallback-конфиги и т.д.).

Провайдер использует ORM-entity-классы и поддерживает кастомизацию:

- `Tenant` (по умолчанию `PhpSoftBox\MultiTenant\Entity\Tenant\Tenant`)
- `Domain` (по умолчанию `PhpSoftBox\MultiTenant\Entity\Tenant\Domain`)

Можно передать свои классы (с дополнительными полями/relations), если они реализуют:

- `TenantEntityInterface`
- `DomainEntityInterface`

Если `database_connection` не задан, по умолчанию используется alias `tenant`.

Пример миграции для этих таблиц находится в:

- `migrations/20260303000100_create_multi_tenant_registry_tables.php`
- `migrations/20260303000200_create_multi_tenant_telegram_bots_table.php`

## Проверка совпадения Config и DB

Если нужно одновременно держать file-config и core БД синхронными, используйте `ConsistentTenantProvider`.

Пример DI:

```php
use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Tenant\Provider\ConfigTenantProvider;
use PhpSoftBox\MultiTenant\Tenant\Provider\ConsistentTenantProvider;
use PhpSoftBox\MultiTenant\Tenant\Provider\DatabaseTenantProvider;
use Psr\Container\ContainerInterface;

use function DI\factory;

return [
    TenantProviderInterface::class => factory(static function (ContainerInterface $container): TenantProviderInterface {
        return new ConsistentTenantProvider(
            primary: new DatabaseTenantProvider($container->get(ConnectionManagerInterface::class), 'default'),
            secondary: new ConfigTenantProvider($container->get(Config::class)),
            enforceParity: true,
        );
    }),
];
```

## Расширяемый Tenant Context (DI-friendly)

Для расширения tenant-настроек без правок ядра:

- `TenantContextFactory` принимает `iterable<TenantExtensionLoaderInterface>`
- каждый loader имеет `key()/priority()/supports(scope)` и добавляет extension в `TenantContext`
- данные достаются через `TenantContext::get('key')`, `TenantContext::get(LoaderClass::class)` или `TenantContext::getTyped(SomeConfig::class)`

Для runtime-инициализации:

- `TenantBootstrapPipeline` принимает `iterable<TenantBootstrapperInterface>`
- bootstrapper имеет `priority()/supports(scope)` + `bootstrap()/teardown()`
- teardown выполняется в обратном порядке

Четкая граница:
- `Loader` только читает/собирает данные и кладет их в `TenantContext` (без side-effects).
- `Bootstrapper` применяет/откатывает runtime side-effects на основе `TenantContext`.

Базовые bootstrappers в компоненте:
- `DatabaseTenantConnectionBootstrapper`
- `TenantPushrRegistryBootstrapper`
- `TenantTelegramRegistryBootstrapper`
- `TenantBroadcastChannelBootstrapper`
- `TenantCacheNamespaceBootstrapper`
- `TenantStorageNamespaceBootstrapper`

Базовые loader-ы в компоненте:
- `TenantPushrCredentialsLoader`
- `DatabaseTelegramBotsLoader` (core table `telegram_bots`, ORM, configurable entity class)

Реализации switcher-ов в компоненте:
- `PushrConfigSwitcher` (runtime override `pushr.app_id/pushr.secret` в `Config`)
- `TelegramBotRegistrySwitcher` (runtime замена bot tokens в `TelegramBotRegistry`)
- `ChannelRegistryPrefixSwitcher` (prefix для broadcaster channel patterns)
- `CacheStoreNamespaceSwitcher` (runtime namespace для `CacheStore`)
- `StoragePathPrefixSwitcher` (runtime path/prefix для `Storage` disks)

Для host-based определения tenant/central domains:
- `CentralDomainPolicy`
- `TenantHostResolver` + `TenantHostResolution`

Пример DI:

```php
use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Context\TenantContextFactory;
use Psr\Container\ContainerInterface;

use function DI\factory;

return [
    TenantContextFactory::class => factory(static function (ContainerInterface $container): TenantContextFactory {
        return new TenantContextFactory([
            $container->get(App\Tenancy\Loader\PushrTenantLoader::class),
            $container->get(App\Tenancy\Loader\TelegramTenantLoader::class),
        ]);
    }),

    TenantBootstrapPipeline::class => factory(static function (ContainerInterface $container): TenantBootstrapPipeline {
        return new TenantBootstrapPipeline([
            $container->get(App\Tenancy\Bootstrap\DatabaseConnectionBootstrapper::class),
            $container->get(App\Tenancy\Bootstrap\TelegramRegistryBootstrapper::class),
        ]);
    }),
];
```

## Важно про создание tenant БД

Для provisioning нового tenant рекомендуется шаблонный подход:

1. создать tenant БД как копию schema существующего template tenant без бизнес-данных
2. оставить данные только в таблице `migrations`
3. дальше поддерживать схему через `tenant:db:migrate`

Команда `tenant:db:provision` поддерживает этот workflow:

- создаёт target БД (если не существует)
- копирует структуру таблиц из template tenant
- копирует данные только из таблицы миграций (`migrations` или `--migrations-table`)
- при непустой target БД требует явный `--drop-existing`
