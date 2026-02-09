<?php

declare(strict_types=1);

namespace Plugs\Multitenancy;

class TenantManager
{
    protected static ?self $instance = null;
    protected $currentTenantId = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function setTenantId($id): void
    {
        $this->currentTenantId = $id;
    }

    public function getTenantId()
    {
        return $this->currentTenantId;
    }

    public function hasTenant(): bool
    {
        return $this->currentTenantId !== null;
    }

    public function clear(): void
    {
        $this->currentTenantId = null;
    }
}
