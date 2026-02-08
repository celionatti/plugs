<?php

declare(strict_types=1);

namespace Plugs\Http\Integration\Concerns;

/** @phpstan-ignore trait.unused */
trait HasFormParams
{
    protected array $data = [];

    public function body(): array
    {
        return $this->data;
    }

    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }
}
