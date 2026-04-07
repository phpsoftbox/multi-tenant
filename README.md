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
- `tenant:auth:role:sync [--scope=all|core|tenant] [--tenant=all] [--dry-run]`
- `tenant:auth:role:assign <user> <role> [--scope=tenant] [--tenant=<id>|all]`
- `tenant:auth:role:discharge <user> <role> [--scope=tenant] [--tenant=<id>|all]`
- `tenant:telegram:poll [--tenant=all] [--bot=<name>] [--once]` (по умолчанию `scope=tenant`)
- `tenant:telegram:webhook [--tenant=all] [--bot=<name>] [--url=<url>]` (по умолчанию `scope=tenant`)
- `tenant:telegram:sync [--tenant=all] [--bot=<name>] [--webhook]` (по умолчанию `scope=tenant`)

`tenant:db:migrate` и `tenant:db:rollback` для каждого tenant активируют runtime DSN по `database_name`/`data.database.name`/`data.database.dsn`.
В логах команды выводят `connection=<alias>, database=<database_name>`, чтобы было видно, какая tenant БД обслуживается.

`tenant:auth:role:*` команды не читают auth-config проекта и не собирают
`RoleSynchronizer`/`UserRoleManager` самостоятельно. Приложение должно
зарегистрировать `ScopedAuthRoleOperationsResolverInterface`, который возвращает
auth-операции для core и tenant runtime:

```php
use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsInterface;
use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsResolverInterface;

final class AppScopedAuthRoleOperationsResolver implements ScopedAuthRoleOperationsResolverInterface
{
    public function core(): ScopedAuthRoleOperationsInterface
    {
        return $this->buildCoreOperations();
    }

    public function tenant(): ScopedAuthRoleOperationsInterface
    {
        return $this->buildTenantOperations();
    }
}
```

Для `assign/discharge` tenant scope требует явный `--tenant=<id>` или
`--tenant=all`, чтобы массовое изменение ролей не запускалось случайно.

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
- `TenantOrmEntityManagerBootstrapper`
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

## Определение tenant в HTTP-запросах

`TenantResolveMiddleware` определяет tenant, записывает его в request-атрибут
`_tenant` и запускает следующий обработчик внутри `TenantRuntimeExecutor` с
HTTP scope. После завершения обработчика, включая исключения, runtime выполняет
teardown и восстанавливает предыдущий контекст.

По умолчанию используется `HostTenantRequestResolver`: host из URI запроса
передаётся в `TenantProviderInterface::findByHost()`. Существующие вызовы
конструктора middleware продолжают работать. Неизвестный или пустой host
приводит к `TenantNotFoundException`. Отдельный `TenantHostResolver` с
`CentralDomainPolicy` служит для различения центральных и tenant-доменов;
`TenantResolveMiddleware` предназначен для маршрутов, где tenant обязателен.

Если API обслуживает несколько tenants на одном домене, можно передавать ID
в заголовке. Пример подключения к существующим provider и runtime:

```php
use PhpSoftBox\MultiTenant\Http\Middleware\TenantResolveMiddleware;
use PhpSoftBox\MultiTenant\Http\Resolver\HeaderTenantRequestResolver;

$middleware = new TenantResolveMiddleware(
    tenants: $tenantProvider,
    tenantRuntime: $tenantRuntime,
    resolver: new HeaderTenantRequestResolver(headerName: 'X-Tenant-Id'),
);
```

Теперь запрос `X-Tenant-Id: 42` вызывает `findById('42')`. Домен может быть общим
для всех tenants и не обязан присутствовать в их настройках. Имя заголовка
настраивается; регистр имени не имеет значения. Значение — строковый ID tenant,
не имя домена, DSN или API-токен. Пробелы по краям ID удаляются.

Для приоритета заголовка с переходом к домену используйте цепочку:

```php
use PhpSoftBox\MultiTenant\Http\Resolver\ChainTenantRequestResolver;
use PhpSoftBox\MultiTenant\Http\Resolver\HeaderTenantRequestResolver;
use PhpSoftBox\MultiTenant\Http\Resolver\HostTenantRequestResolver;

$resolver = new ChainTenantRequestResolver([
    new HeaderTenantRequestResolver(),
    new HostTenantRequestResolver(),
]);
```

Передайте `$resolver` в одноимённый аргумент middleware. Порядок элементов задаёт
приоритет: первый найденный tenant завершает поиск. В примере заголовок имеет
приоритет, даже если домен соответствует другому tenant.

- Отсутствующий заголовок позволяет перейти к следующему resolver.
- Переданный пустой заголовок, несколько значений (в том числе через запятую)
  или неизвестный ID вызывают `TenantNotFoundException` без перехода к домену.
- В режиме только заголовка его отсутствие также приводит к
  `TenantNotFoundException` в middleware; обработчик не запускается.

Заголовок выбирает tenant, но не предоставляет доступ к нему. Приложение должно
проверить право аутентифицированного пользователя или API-токена работать с
выбранным tenant до выполнения бизнес-операции. Resolver не заменяет Auth и
не добавляет отдельную политику проверки `TenantDefinition::enabled`.

Собственный способ выбора (например, по атрибуту запроса) реализуется через
`TenantRequestResolverInterface::resolve($request, $tenants): ?TenantDefinition`.
Middleware передаёт общий provider после `reload()`, если provider поддерживает
`ReloadableTenantProviderInterface`. Поэтому resolver не должен хранить свой
provider или повторно обновлять его. `null` означает, что источник не задан и
цепочка может продолжить поиск; заданный некорректный источник должен приводить
к исключению. `HostTenantRequestResolver` сохраняет прежнее строгое поведение:
пустой URI host является ошибкой.

Для диагностики middleware сохраняет событие `tenant.resolve`; вложенное
событие поиска теперь называется `tenant.request.resolve` вместо
`tenant.provider.find_by_host` и содержит класс resolver. Если приложение
фильтрует profiler-события по старому имени, фильтр необходимо обновить.

## Tenant-aware ORM

Для приложений, где tenant БД активируется в runtime, компонент предоставляет
тонкий tenant-aware слой поверх `phpsoftbox/orm`:

- `TenantEntityManagerRegistryInterface`
- `TenantEntityManagerRegistry`
- `TenantEntityManagerInterface`
- `TenantEntityManager`

`TenantEntityManagerRegistry` оборачивает базовый
`PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface` и добавляет
`tenant(bool $write = true)`. Его `forEntity()` сначала получает имя connection из
ORM metadata, а затем использует тот же кеш, что `tenant()`/`forConnection()`.
По умолчанию tenant connection называется
`tenant`, но имя можно переопределить в конструкторе.

```php
use PhpSoftBox\MultiTenant\Orm\TenantEntityManager;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerInterface;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistry;
use PhpSoftBox\MultiTenant\Orm\TenantEntityManagerRegistryInterface;
use PhpSoftBox\MultiTenant\Bootstrap\TenantOrmEntityManagerBootstrapper;
use PhpSoftBox\Orm\Contracts\EntityAwareEntityManagerRegistryInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerRegistryInterface as BaseEntityManagerRegistryInterface;
use Psr\Container\ContainerInterface;

use function DI\factory;
use function DI\get;

return [
    TenantEntityManagerRegistryInterface::class => factory(
        static fn (ContainerInterface $container): TenantEntityManagerRegistryInterface => new TenantEntityManagerRegistry(
            registry: $container->get(BaseEntityManagerRegistryInterface::class),
            tenantConnectionName: 'tenant',
        ),
    ),

    EntityAwareEntityManagerRegistryInterface::class => get(TenantEntityManagerRegistryInterface::class),

    TenantOrmEntityManagerBootstrapper::class => factory(
        static fn (ContainerInterface $container): TenantOrmEntityManagerBootstrapper => new TenantOrmEntityManagerBootstrapper(
            $container->get(TenantEntityManagerRegistryInterface::class),
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
процессах после tenant switch/teardown. Перед удалением manager из кеша registry
вызывает `UnitOfWorkInterface::clear()`: это освобождает identity map, scheduled
operations и ownership сущностей в общем `EntityRuntimeRegistry`.

Для lifecycle-кода используйте `resetTenant()`: registry сам применит alias,
переданный ему как `tenantConnectionName`. Благодаря этому bootstrapper и
приложение не дублируют имя tenant connection.

Если приложение предоставляет собственную реализацию
`TenantEntityManagerRegistryInterface`, после обновления компонента в ней также
нужно реализовать `resetTenant()`. Метод должен удалять только tenant manager-ы,
не затрагивая default/core connection.

### Время жизни tenant EntityManager

Tenant EntityManager имеет время жизни одного вызова
`TenantRuntimeExecutor::run()`. `TenantOrmEntityManagerBootstrapper` сбрасывает
его дважды:

1. после активации tenant DSN, перед выполнением callback;
2. перед деактивацией DSN, в teardown текущего scope.

Это важно для HTTP-запросов и особенно для долгоживущих CLI, queue, scheduler и
WebSocket worker. После простоя соединение предыдущего EntityManager могло быть
закрыто сервером БД. Новый runtime scope не переиспользует такой manager и получает
новое connection через уже активированный tenant DSN.

Порядок задаётся priority bootstrapper-ов:

```text
bootstrap:
1000 DatabaseTenantConnectionBootstrapper::bootstrap()
 950 TenantOrmEntityManagerBootstrapper::bootstrap()

teardown:
 950 TenantOrmEntityManagerBootstrapper::teardown()
1000 DatabaseTenantConnectionBootstrapper::teardown()
```

Pipeline выполняет teardown в обратном порядке, поэтому дополнительная ручная
координация не требуется. Для читаемости регистрируйте bootstrappers в таком же
порядке:

```php
use PhpSoftBox\MultiTenant\Bootstrap\DatabaseTenantConnectionBootstrapper;
use PhpSoftBox\MultiTenant\Bootstrap\TenantBootstrapPipeline;
use PhpSoftBox\MultiTenant\Bootstrap\TenantOrmEntityManagerBootstrapper;

$pipeline = new TenantBootstrapPipeline([
    $container->get(DatabaseTenantConnectionBootstrapper::class),
    $container->get(TenantOrmEntityManagerBootstrapper::class),
    // Остальные tenant bootstrappers.
]);
```

Bootstrapper поддерживает все `TenantRuntimeScope`: HTTP, CLI, Queue, Scheduler и
Websocket. Во вложенном runtime `tenant A → tenant B → tenant A` manager tenant A
сбрасывается перед входом в B. После teardown B connection manager восстанавливает
DSN tenant A, а следующее обращение создаёт для A новый EntityManager.

Долгоживущие сервисы должны зависеть от `TenantEntityManagerInterface`: это
ленивый proxy, который получает актуальный manager при каждой операции. Нельзя
сохранять конкретный tenant `EntityManagerInterface` или загруженные им entity и
передавать их в другой runtime scope.

Сброс не затрагивает core/default EntityManager и не повторяет SQL-запросы.
Исключения создания connection или выполнения callback не скрываются
`TenantRuntimeExecutor`; неперехваченное прикладным кодом исключение выходит в
queue worker и обрабатывается его штатной retry/failure policy.

`runtimeRegistry()` делегируется базовому ORM registry и остаётся одним и тем же
объектом при смене tenant. Его можно безопасно передать в Resource
`OrmRelationStateProvider`: relation-state будет найден по экземпляру entity, а не
по текущему tenant или однажды выбранному `UnitOfWork`.

Router binding должен указывать на tenant-aware registry, иначе route entity будет
загружена отдельным manager базового registry:

```php
EntityAwareEntityManagerRegistryInterface::class => get(TenantEntityManagerRegistryInterface::class),
```

## Tenant changelog

Для tenant-aware аудита ORM изменений доступны:

- `TenantMongoEntityChangeLogger`
- `TenantEntityChangeContextResolver`
- `EntityChangeInitiatorResolverInterface`

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

`TenantEntityChangeContextResolver` добавляет в changelog context:

- `initiatorId` из подключенного `EntityChangeInitiatorResolverInterface`;
- `initiatorType = user`, если initiator найден, иначе `system`;
- metadata `tenant_id` из `TenantContextResolver`;
- metadata `request_method` и `request_path`, если передан request.

```php
use PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator\AuthManagerEntityChangeInitiatorResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator\ChainEntityChangeInitiatorResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator\RequestAttributeEntityChangeInitiatorResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\TenantEntityChangeContextResolver;

$initiatorResolver = new ChainEntityChangeInitiatorResolver([
    new RequestAttributeEntityChangeInitiatorResolver($request),
    new AuthManagerEntityChangeInitiatorResolver(
        request: $request,
        auth: $auth,
        guard: 'tenant',
    ),
]);

$contextResolver = new TenantEntityChangeContextResolver(
    tenantResolver: $tenantContextResolver,
    initiatorResolver: $initiatorResolver,
    request: $request,
);
```

`AuthManagerEntityChangeInitiatorResolver` читает пользователя из одного guard.
Если нужен fallback между guards, соберите его явно через
`ChainEntityChangeInitiatorResolver`.

По умолчанию `RequestAttributeEntityChangeInitiatorResolver` читает scalar-id из
request attribute `user_id`, а пользователя из request attribute `user`.
Если приложение использует другие request attributes, укажите их явно:

```php
$initiatorResolver = new RequestAttributeEntityChangeInitiatorResolver(
    request: $request,
    userIdAttribute: 'auth_user_id',
    userAttribute: '_authUser',
);
```

В CLI/worker можно передать initiator явно:

```php
use PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator\StaticEntityChangeInitiatorResolver;
use PhpSoftBox\MultiTenant\Orm\ChangeLog\TenantEntityChangeContextResolver;

$contextResolver = new TenantEntityChangeContextResolver(
    tenantResolver: $tenantContextResolver,
    initiatorResolver: new StaticEntityChangeInitiatorResolver($job->initiatorUserId),
);
```

Если initiator не передан, resolver вернет system-context и, если tenant context
активен, добавит только `tenant_id`.

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
