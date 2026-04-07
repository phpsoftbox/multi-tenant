# PhpSoftBox MultiTenant

`phpsoftbox/multi-tenant` — расширение для multi-tenant сценариев:

- выбор tenant в CLI (`--tenant`, по умолчанию `all`)
- tenant-aware миграции (`tenant:db:migrate`, `tenant:db:rollback`)
- provisioning tenant БД из template (`tenant:db:provision`)
- tenant-aware запуск Pushr (`tenant:pushr:serve`, `tenant:pushr:serve:registry`) по tenant-приложениям
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
- `tenant:pushr:serve:registry [--tenant=all] [--host=0.0.0.0] [--port=8080] [--max-skew=300] [--without-default-app]`
- `tenant:queue:core:run [--max-jobs=0]`
- `tenant:queue:tenant:run [--tenant=all] [--max-jobs=0]`
- `tenant:telegram:poll [--tenant=all] [--bot=<name>] [--once]` (по умолчанию `scope=tenant`)
- `tenant:telegram:webhook [--tenant=all] [--bot=<name>] [--url=<url>]` (по умолчанию `scope=tenant`)
- `tenant:telegram:sync [--tenant=all] [--bot=<name>] [--webhook]` (по умолчанию `scope=tenant`)

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
                'notifications_config' => [
                    'mail' => [
                        'smtp' => [
                            'from' => 'no-reply@tenant1.chegdesklad.local',
                        ],
                        'brand' => [
                            'name' => 'Tenant 1',
                            'caption' => 'Tenant Portal',
                            'logo_url' => 'https://tenant1.chegdesklad.local/assets/logo.svg',
                            'unsubscribe_url' => 'https://tenant1.chegdesklad.local/profile/subscriptions',
                            'unsubscribe_label' => 'Управлять подпиской',
                        ],
                    ],
                    'notifications' => [
                        'email' => [
                            'view_path' => 'resources/views/tenant',
                        ],
                    ],
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
Для Telegram поддерживаются оба формата:
- `telegram_bots` / `telegram.bots` (массив ботов),
- `telegram` (single-bot: `bot_name`/`bot_code`/`bot_token`).
Tenant-боты применяются только для кодов, которые существуют в core `telegram.bots`.

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
- `TenantNotificationConfigBootstrapper`
- `TenantPushrRegistryBootstrapper`
- `TenantTelegramRegistryBootstrapper`
- `TenantBroadcastChannelBootstrapper`
- `TenantCacheNamespaceBootstrapper`
- `TenantStorageNamespaceBootstrapper`

Базовые loader-ы в компоненте:
- `TenantNotificationConfigLoader`
- `TenantPushrCredentialsLoader`
- `DatabaseTelegramBotsLoader` (core table `telegram_bots`, ORM, configurable entity class)

Реализации switcher-ов в компоненте:
- `NotificationConfigSwitcher` (runtime override выбранных config-ключей, например `mail.*`/`notifications.*`)
- `PushrConfigSwitcher` (runtime override `pushr.app_id/pushr.secret` в `Config`)
- `TelegramBotRegistrySwitcher` (runtime замена bot tokens в `TelegramBotRegistry`)
- `ChannelRegistryPrefixSwitcher` (prefix для broadcaster channel patterns)
- `CacheStoreNamespaceSwitcher` (runtime namespace для `CacheStore`)
- `StoragePathPrefixSwitcher` (runtime path/prefix для `Storage` disks)

`TenantDefinition::data` уже используется для fallback-переопределений (при включенных соответствующих loader/bootstrapper):
- Pushr: `data.pushr.app_id` / `data.pushr.secret` и `data.pushr_app_id` / `data.pushr_secret`
- Notifications: `data.notifications_config` или `data.notifications.config` (например `mail.smtp.from`, `mail.brand.*`, `notifications.email.view_path`)

Для host-based определения tenant/central domains:
- `CentralDomainPolicy`
- `TenantHostResolver` + `TenantHostResolution`

## Tenant-aware ORM

Для приложений, где tenant БД активируется в runtime, компонент предоставляет
тонкий tenant-aware слой поверх `phpsoftbox/orm`:

- `TenantEntityManagerRegistryInterface`
- `TenantEntityManagerRegistry`
- `TenantEntityManagerInterface`
- `TenantEntityManager`

`TenantEntityManagerRegistry` оборачивает базовый
`PhpSoftBox\Orm\Contracts\EntityManagerRegistryInterface` и добавляет
`tenant(bool $write = true)`. По умолчанию tenant connection называется
`tenant`, но имя можно переопределить в конструкторе.

```php
use PhpSoftBox\MultiTenant\Orm\TenantEntityManager;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerInterface;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistry;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerRegistryInterface as BaseEntityManagerRegistryInterface;
use Psr\Container\ContainerInterface;

use function DI\factory;

return [
    TenantEntityManagerRegistryInterface::class => factory(
        static fn (ContainerInterface $container): TenantEntityManagerRegistryInterface => new TenantEntityManagerRegistry(
            registry: $container->get(BaseEntityManagerRegistryInterface::class),
            tenantConnectionName: 'tenant',
        ),
    ),

    TenantEntityManagerInterface::class => factory(
        static fn (ContainerInterface $container): TenantEntityManagerInterface => new TenantEntityManager(
            $container->get(TenantEntityManagerRegistryInterface::class),
        ),
    ),
];
```

`TenantEntityManager` реализует обычный ORM `EntityManagerInterface`, но все
операции делегирует в `TenantEntityManagerRegistry::tenant()`. Поэтому сервисы
приложения могут зависеть от `TenantEntityManagerInterface` и не передавать имя
tenant connection вручную в каждом repository/query.

Registry кеширует entity-manager по паре `read/write + connection`. Метод
`reset()` очищает кеш полностью, а `reset('tenant')` очищает tenant connection и
runtime-подключения с префиксом `tenant.`. Это полезно в long-running CLI/worker
процессах после tenant switch/teardown.

## Tenant changelog

Для tenant-aware аудита ORM изменений доступны:

- `TenantMongoEntityChangeLogger`
- `TenantUserEntityChangeContextResolver`

`TenantMongoEntityChangeLogger` пишет changelog в MongoDB текущего tenant. Он
ожидает provider с методом `collection(string): object`, а collection object
должен иметь метод `insertOne(array)`.

```php
use PhpSoftBox\MultiTenant\Orm\ChangeLog\TenantMongoEntityChangeLogger;

$logger = new TenantMongoEntityChangeLogger(
    tenantMongo: $tenantMongo,
    logger: $psrLogger,
    collection: 'entity_changelog',
    technicalFields: ['created_datetime', 'updated_datetime', 'deleted_datetime'],
);
```

Update-записи, где изменились только технические поля из `technicalFields`,
пропускаются. Ошибки записи в MongoDB не прерывают основной ORM flow: logger
пишет warning в PSR logger, если он передан.

Подключение к ORM выполняется через стандартный changelog handler сущности:

```php
use PhpSoftBox\MultiTenant\Orm\ChangeLog\TenantMongoEntityChangeLogger;
use PhpSoftBox\Orm\Attribute\Changelog;

#[Changelog(logHandler: TenantMongoEntityChangeLogger::class)]
final class Product
{
}
```

`TenantUserEntityChangeContextResolver` добавляет в changelog context:

- `initiatorId` из request attributes `user_id` / `auth_user_id`, request user
  attributes или `AuthManager`;
- `initiatorType = user`, если initiator найден, иначе `system`;
- metadata `tenant_id` из `TenantContextResolver`;
- metadata `request_method` и `request_path`, если передан request.

```php
use PhpSoftBox\MultiTenant\Orm\ChangeLog\TenantUserEntityChangeContextResolver;

$contextResolver = new TenantUserEntityChangeContextResolver(
    tenantResolver: $tenantContextResolver,
    request: $request,
    auth: $auth,
    guards: [null, 'tenant'],
);
```

В CLI/worker можно не передавать request/auth. В этом случае resolver вернет
system-context и, если tenant context активен, добавит только `tenant_id`.

## Profiler

Компонент поддерживает `phpsoftbox/profiler` через:

- `MultiTenantProfilerCollector`
- `MultiTenantProfilerExtension`

В trace появляется section `multi_tenant` с событиями tenant lifecycle:

- `tenant.resolve`
- `tenant.provider.reload`
- `tenant.provider.find_by_host`
- `tenant.runtime`
- `tenant.bootstrap`
- `tenant.bootstrap.teardown`
- `tenant.bootstrap.rollback`
- `tenant.connection.activate`
- `tenant.connection.create`
- `tenant.connection.reuse`
- `tenant.connection.deactivate`

Runtime tenant-подключения, которые создает `TenantAwareConnectionManager`, должны получать
тот же `ProfilerInterface` и `DatabaseProfilerCollector`, что и основная `DatabaseFactory`.
Тогда SQL из tenant БД попадает в общий `database` section, а не теряется как отдельный runtime connection.

В событиях не сохраняется DSN. Разрешены только безопасные теги: `tenant_id`, `tenant_name`,
`host`, `scope`, `connection`, `connection_alias`, `bootstrapper`.

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
