<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Cli;

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\CliApp\Command\CommandRegistryInterface;
use PhpSoftBox\CliApp\Command\OptionDefinition;
use PhpSoftBox\CliApp\Loader\CommandProviderInterface;
use PhpSoftBox\MultiTenant\Cli\Telegram\TelegramPollScopeHandler;
use PhpSoftBox\MultiTenant\Cli\Telegram\TelegramSyncScopeHandler;
use PhpSoftBox\MultiTenant\Cli\Telegram\TelegramWebhookScopeHandler;

final class TenantCommandProvider implements CommandProviderInterface
{
    public function register(CommandRegistryInterface $registry): void
    {
        $registry->register(Command::define(
            name: 'tenant:list',
            description: 'Показать список tenant',
            signature: [
                new OptionDefinition(
                    name: 'all',
                    short: 'a',
                    description: 'Показать также отключенные tenant',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TenantListHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:config:check',
            description: 'Проверить совпадение tenant-реестра из config и core DB',
            signature: [
                new OptionDefinition(
                    name: 'primary',
                    short: 'P',
                    description: 'Primary provider: database|config (по умолчанию берется из tenancy.providers.primary)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'connection',
                    short: 'c',
                    description: 'Connection для DatabaseTenantProvider (по умолчанию tenancy.providers.database.connection)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
            ],
            handler: TenantConfigCheckHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:db:migrate',
            description: 'Применить миграции tenant БД',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: 'p',
                    description: 'Относительный путь внутри базы миграций',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'fail-fast',
                    short: 'f',
                    description: 'Остановиться на первой ошибке',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TenantDbMigrateHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:db:rollback',
            description: 'Откатить миграции tenant БД',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: 'p',
                    description: 'Относительный путь внутри базы миграций',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'steps',
                    short: 's',
                    description: 'Количество откатываемых миграций',
                    required: false,
                    default: 1,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'fail-fast',
                    short: 'f',
                    description: 'Остановиться на первой ошибке',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TenantDbRollbackHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:mongo:migrate',
            description: 'Применить mongo-миграции tenant',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: 'p',
                    description: 'Относительный путь внутри базы миграций',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'fail-fast',
                    short: 'f',
                    description: 'Остановиться на первой ошибке',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TenantMongoMigrateHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:mongo:rollback',
            description: 'Откатить mongo-миграции tenant',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: 'p',
                    description: 'Относительный путь внутри базы миграций',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'steps',
                    short: 's',
                    description: 'Количество откатываемых миграций',
                    required: false,
                    default: 1,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'fail-fast',
                    short: 'f',
                    description: 'Остановиться на первой ошибке',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TenantMongoRollbackHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:db:provision',
            description: 'Скопировать schema из template tenant в target tenant (данные только migrations)',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'template',
                    short: 'T',
                    description: 'Template tenant ID (если не передан, читается из tenancy.provision.template_tenant)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'migrations-table',
                    short: 'm',
                    description: 'Таблица миграций для переноса данных',
                    required: false,
                    default: 'migrations',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'drop-existing',
                    short: 'd',
                    description: 'Удалять существующие таблицы в target БД перед копированием',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'fail-fast',
                    short: 'f',
                    description: 'Остановиться на первой ошибке',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TenantDbProvisionHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:provision:dispatch',
            description: 'Поставить задачу provision tenant в queue',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'template',
                    short: 'T',
                    description: 'Template tenant ID (если не передан, читается из tenancy.provision.template_tenant)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'owner-phone',
                    short: 'p',
                    description: 'Телефон первого владельца tenant (опционально)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'owner-name',
                    short: 'n',
                    description: 'Имя первого владельца tenant (опционально)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'owner-email',
                    short: 'e',
                    description: 'Email первого владельца tenant (опционально)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'no-confirm-phone',
                    short: null,
                    description: 'Не подтверждать телефон владельца автоматически',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'priority',
                    short: 'P',
                    description: 'Приоритет queue job',
                    required: false,
                    default: 0,
                    type: 'int',
                ),
            ],
            handler: TenantProvisionDispatchHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:provision:run',
            description: 'Запустить provision tenant синхронно (без queue)',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'template',
                    short: 'T',
                    description: 'Template tenant ID (если не передан, читается из tenancy.provision.template_tenant)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'owner-phone',
                    short: 'p',
                    description: 'Телефон первого владельца tenant (опционально)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'owner-name',
                    short: 'n',
                    description: 'Имя первого владельца tenant (опционально)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'owner-email',
                    short: 'e',
                    description: 'Email первого владельца tenant (опционально)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'no-confirm-phone',
                    short: null,
                    description: 'Не подтверждать телефон владельца автоматически',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TenantProvisionRunHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:pushr:serve',
            description: 'Запустить Pushr сервер с tenant-приложениями из реестра',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'host',
                    short: 'H',
                    description: 'Хост',
                    required: false,
                    default: '0.0.0.0',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'port',
                    short: 'p',
                    description: 'Порт',
                    required: false,
                    default: 8080,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'max-skew',
                    short: 'k',
                    description: 'Допустимое смещение времени (секунды)',
                    required: false,
                    default: 300,
                    type: 'int',
                ),
            ],
            handler: TenantPushrServeHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:queue:core:run',
            description: 'Запустить queue worker в core-режиме (один worker, tenant_id в payload)',
            signature: [
                new OptionDefinition(
                    name: 'max-jobs',
                    short: 'm',
                    description: 'Количество задач для обработки (0 = без лимита)',
                    required: false,
                    default: 0,
                    type: 'int',
                ),
            ],
            handler: TenantQueueCoreRunHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:queue:tenant:run',
            description: 'Запустить queue worker в tenant-режиме (по каждому tenant отдельно)',
            signature: [
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (по умолчанию)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'max-jobs',
                    short: 'm',
                    description: 'Количество задач на tenant (0 = без лимита)',
                    required: false,
                    default: 0,
                    type: 'int',
                ),
            ],
            handler: TenantQueueTenantRunHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:auth:sync',
            description: 'Синхронизирует роли и пермишены для core и/или tenant БД',
            signature: [
                new OptionDefinition(
                    name: 'scope',
                    short: 's',
                    description: 'Область синхронизации: all|core|tenant',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    short: 't',
                    description: 'Tenant ID или all (для scope=tenant)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    short: 'd',
                    description: 'Показать план синхронизации без записи в БД',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TenantAuthSyncHandler::class,
        ));

        $registry->register(Command::define(
            name: 'telegram:poll',
            description: 'Запускает long-polling Telegram для core или tenant контура',
            signature: [
                new OptionDefinition(
                    name: 'scope',
                    short: null,
                    description: 'Контур: core|tenant|all',
                    required: false,
                    default: 'core',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    short: null,
                    description: 'Tenant ID или all (для scope=tenant|all)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'bot',
                    short: 'b',
                    description: 'Имя бота из конфигурации',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'timeout',
                    short: 't',
                    description: 'Таймаут long polling (сек)',
                    required: false,
                    default: 25,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'sleep',
                    short: 's',
                    description: 'Пауза между запросами (сек)',
                    required: false,
                    default: 1,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'offset',
                    short: 'o',
                    description: 'Начальный offset',
                    required: false,
                    default: 0,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'debug',
                    short: 'd',
                    description: 'Показывать ход выполнения',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'all',
                    short: 'a',
                    description: 'Опросить всех ботов',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'once',
                    short: null,
                    description: 'Выполнить один запрос и завершиться',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TelegramPollScopeHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:telegram:poll',
            description: 'Запускает long-polling Telegram для tenant контура',
            signature: [
                new OptionDefinition(
                    name: 'scope',
                    short: null,
                    description: 'Контур: core|tenant|all (по умолчанию tenant)',
                    required: false,
                    default: 'tenant',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    short: null,
                    description: 'Tenant ID или all',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'bot',
                    short: 'b',
                    description: 'Имя бота из конфигурации',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'timeout',
                    short: 't',
                    description: 'Таймаут long polling (сек)',
                    required: false,
                    default: 25,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'sleep',
                    short: 's',
                    description: 'Пауза между запросами (сек)',
                    required: false,
                    default: 1,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'offset',
                    short: 'o',
                    description: 'Начальный offset',
                    required: false,
                    default: 0,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'debug',
                    short: 'd',
                    description: 'Показывать ход выполнения',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'all',
                    short: 'a',
                    description: 'Опросить всех ботов',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'once',
                    short: null,
                    description: 'Выполнить один запрос и завершиться',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TelegramPollScopeHandler::class,
        ));

        $registry->register(Command::define(
            name: 'telegram:webhook',
            description: 'Регистрирует webhook URL для Telegram-бота (core/tenant)',
            signature: [
                new OptionDefinition(
                    name: 'scope',
                    short: null,
                    description: 'Контур: core|tenant|all',
                    required: false,
                    default: 'core',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    short: null,
                    description: 'Tenant ID или all (для scope=tenant|all)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'bot',
                    short: 'b',
                    description: 'Имя бота из конфигурации (по умолчанию default)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'url',
                    short: 'u',
                    description: 'Полный URL вебхука',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'base-url',
                    short: null,
                    description: 'Базовый URL, если URL не передан',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: null,
                    description: 'Путь вебхука (по умолчанию /telegram/{bot}/webhook)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'info',
                    short: 'i',
                    description: 'Показать текущую информацию о webhook',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'debug',
                    short: 'd',
                    description: 'Показывать подробности',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TelegramWebhookScopeHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:telegram:webhook',
            description: 'Регистрирует webhook URL для Telegram-ботов tenant контура',
            signature: [
                new OptionDefinition(
                    name: 'scope',
                    short: null,
                    description: 'Контур: core|tenant|all (по умолчанию tenant)',
                    required: false,
                    default: 'tenant',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    short: null,
                    description: 'Tenant ID или all',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'bot',
                    short: 'b',
                    description: 'Имя бота из конфигурации (по умолчанию default)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'url',
                    short: 'u',
                    description: 'Полный URL вебхука',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'base-url',
                    short: null,
                    description: 'Базовый URL, если URL не передан',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: null,
                    description: 'Путь вебхука (по умолчанию /telegram/{bot}/webhook)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'info',
                    short: 'i',
                    description: 'Показать текущую информацию о webhook',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'debug',
                    short: 'd',
                    description: 'Показывать подробности',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
            ],
            handler: TelegramWebhookScopeHandler::class,
        ));

        $registry->register(Command::define(
            name: 'telegram:sync',
            description: 'Обновляет команды Telegram-бота для core или tenant контура',
            signature: [
                new OptionDefinition(
                    name: 'scope',
                    short: null,
                    description: 'Контур: core|tenant|all',
                    required: false,
                    default: 'core',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    short: null,
                    description: 'Tenant ID или all (для scope=tenant|all)',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'bot',
                    short: 'b',
                    description: 'Имя бота (по умолчанию default)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'webhook',
                    short: 'w',
                    description: 'Обновить webhook после sync',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'base-url',
                    short: null,
                    description: 'Базовый URL для webhook (если --webhook)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: null,
                    description: 'Путь webhook (если --webhook)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
            ],
            handler: TelegramSyncScopeHandler::class,
        ));

        $registry->register(Command::define(
            name: 'tenant:telegram:sync',
            description: 'Обновляет команды Telegram-ботов для tenant контура',
            signature: [
                new OptionDefinition(
                    name: 'scope',
                    short: null,
                    description: 'Контур: core|tenant|all (по умолчанию tenant)',
                    required: false,
                    default: 'tenant',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'tenant',
                    short: null,
                    description: 'Tenant ID или all',
                    required: false,
                    default: 'all',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'bot',
                    short: 'b',
                    description: 'Имя бота (по умолчанию default)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'webhook',
                    short: 'w',
                    description: 'Обновить webhook после sync',
                    flag: true,
                    required: false,
                    default: false,
                    type: 'bool',
                ),
                new OptionDefinition(
                    name: 'base-url',
                    short: null,
                    description: 'Базовый URL для webhook (если --webhook)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'path',
                    short: null,
                    description: 'Путь webhook (если --webhook)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
            ],
            handler: TelegramSyncScopeHandler::class,
        ));
    }
}
