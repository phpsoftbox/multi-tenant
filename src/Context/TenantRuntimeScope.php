<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Context;

enum TenantRuntimeScope: string
{
    case Cli       = 'cli';
    case Http      = 'http';
    case Queue     = 'queue';
    case Scheduler = 'scheduler';
    case Websocket = 'websocket';
}
