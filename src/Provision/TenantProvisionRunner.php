<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProvisionRunnerInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\MultiTenant\Tenant\TenantSelector;
use RuntimeException;

use function count;
use function is_string;
use function trim;

final readonly class TenantProvisionRunner implements TenantProvisionRunnerInterface
{
    public function __construct(
        private TenantSelector $selector,
        private Config $config,
        private TenantProvisionPipelineFactory $pipelineFactory,
    ) {
    }

    public function run(TenantProvisionPayload $payload): TenantProvisionContext
    {
        $target = $this->resolveSingleTenant($payload->tenantId, true, 'target');

        $templateId = $payload->templateTenantId;
        if (!is_string($templateId) || trim($templateId) === '') {
            $templateId = $this->config->get('tenancy.provision.template_tenant', null);
        }

        if (!is_string($templateId) || trim($templateId) === '') {
            throw new RuntimeException(
                'Не указан template tenant. Передайте --template=<id> или настройте tenancy.provision.template_tenant.',
            );
        }

        $templateId = trim($templateId);

        $template = $this->resolveSingleTenant($templateId, false, 'template');
        if (!$template->enabled) {
            throw new RuntimeException('Template tenant отключен: ' . $template->id);
        }

        if ($target->id === $template->id) {
            throw new RuntimeException('Template и target tenant должны отличаться: ' . $target->id);
        }

        $context = new TenantProvisionContext(
            tenant: $target,
            templateTenant: $template,
            payload: $payload,
        );

        return $this->pipelineFactory->create()->run($context);
    }

    private function resolveSingleTenant(string $tenantId, bool $onlyEnabled, string $label): TenantDefinition
    {
        $selected = $this->selector->select($tenantId, $onlyEnabled);
        if (count($selected) !== 1) {
            throw new RuntimeException('Provision ' . $label . ' должен разрешаться в один tenant: ' . $tenantId);
        }

        return $selected[0];
    }
}
