<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

class ViewController
{
    public function handle(string $viewName, array $data = [])
    {
        return view($viewName, $data);
    }
}
