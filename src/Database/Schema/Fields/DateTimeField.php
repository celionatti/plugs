<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * DateTimeField
 *
 * Represents a datetime model attribute.
 */
class DateTimeField extends Field
{
    protected ?string $format = null;
    protected ?string $beforeDate = null;
    protected ?string $afterDate = null;

    /**
     * Set the expected datetime format.
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * The date must be before the given date.
     */
    public function before(string $date): static
    {
        $this->beforeDate = $date;

        return $this;
    }

    /**
     * The date must be after the given date.
     */
    public function after(string $date): static
    {
        $this->afterDate = $date;

        return $this;
    }

    public function getCastType(): string
    {
        if ($this->format) {
            return "datetime:{$this->format}";
        }

        return 'datetime';
    }

    protected function getTypeRules(): array
    {
        $rules = ['date'];

        if ($this->format) {
            $rules[] = "date_format:{$this->format}";
        }

        if ($this->beforeDate) {
            $rules[] = "before:{$this->beforeDate}";
        }

        if ($this->afterDate) {
            $rules[] = "after:{$this->afterDate}";
        }

        return $rules;
    }
}
