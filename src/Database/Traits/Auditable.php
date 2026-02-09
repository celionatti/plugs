<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Models\Audit;
use Plugs\Security\Auth\AuthManager;

trait Auditable
{
    protected ?string $auditReason = null;

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->auditEvent('created');
        });

        static::updated(function ($model, $context) {
            $model->auditEvent('updated', $context['dirty'] ?? []);
        });

        static::deleted(function ($model) {
            $model->auditEvent('deleted');
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->auditEvent('restored');
            });
        }
    }

    /**
     * Set a custom reason for the current audit record.
     */
    public function setAuditReason(string $reason): self
    {
        $this->auditReason = $reason;
        return $this;
    }

    /**
     * Log an audit event.
     */
    protected function auditEvent(string $event, array $dirty = []): void
    {
        $oldValues = [];
        $newValues = [];

        if ($event === 'updated') {
            foreach ($dirty as $key => $value) {
                $oldValues[$key] = $this->original[$key] ?? null;
                $newValues[$key] = $value;
            }
        } elseif ($event === 'created') {
            $newValues = $this->attributes;
        } elseif ($event === 'deleted') {
            $oldValues = $this->attributes;
        }

        Audit::create([
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'user_id' => $this->getAuditUserId(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $this->auditReason,
            'context' => $this->getAuditContext(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $this->auditReason = null; // Reset for next operation
    }

    protected function getAuditUserId()
    {
        if (class_exists(\Plugs\Facades\Auth::class)) {
            return \Plugs\Facades\Auth::id();
        }
        return null;
    }

    protected function getAuditContext(): array
    {
        return [
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        ];
    }
}
