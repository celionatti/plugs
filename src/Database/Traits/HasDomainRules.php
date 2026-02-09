<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use DomainException;

/**
 * Trait HasDomainRules
 *
 * Provides hooks for domain-level validation of updates and deletions,
 * and support for guarded state transitions.
 */
trait HasDomainRules
{
    /**
     * Determine if the model can be updated.
     *
     * @param array $dirty Attributes that have changed.
     * @return bool
     */
    public function canBeUpdated(array $dirty = []): bool
    {
        return true;
    }

    /**
     * Determine if the model can be deleted.
     *
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        return true;
    }

    /**
     * Define allowed state transitions for specific attributes.
     *
     * Format: ['field_name' => ['current_state' => ['allowed_next_state1', 'allowed_next_state2']]]
     *
     * @return array
     */
    protected function transitions(): array
    {
        return [];
    }

    /**
     * Validate that state transitions for changed attributes are allowed.
     *
     * @throws DomainException
     */
    protected function validateStateTransitions(): void
    {
        $dirty = $this->getDirty();
        $transitions = $this->transitions();

        foreach ($dirty as $key => $newValue) {
            if (isset($transitions[$key])) {
                $oldValue = $this->original[$key] ?? null;
                $allowed = $transitions[$key][$oldValue] ?? null;

                if ($allowed !== null && !is_array($allowed)) {
                    $allowed = [$allowed];
                }

                if ($allowed !== null && !in_array($newValue, $allowed)) {
                    $oldDisplay = is_null($oldValue) ? 'NULL' : (string) $oldValue;
                    $newDisplay = is_null($newValue) ? 'NULL' : (string) $newValue;

                    throw new DomainException(
                        "Invalid state transition for [{$key}]: cannot transition from [{$oldDisplay}] to [{$newDisplay}]."
                    );
                }
            }
        }
    }
}
