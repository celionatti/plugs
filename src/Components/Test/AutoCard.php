<?php

namespace App\Components\Test;

class AutoCard
{
    public string $message = 'Hello from Auto Discovery!';
    public string $status;

    public function __construct(string $status = 'success')
    {
        $this->status = $status;
    }
}
