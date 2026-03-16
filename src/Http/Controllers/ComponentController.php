<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Http\Message\ServerRequest;
use Plugs\Http\ResponseFactory;
use Plugs\Facades\Crypt;
use Plugs\Facades\View;
use Plugs\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class ComponentController
{
    /**
     * Render a component for lazy loading
     *
     * @param ServerRequest $request
     * @return ResponseInterface
     */
    public function render(ServerRequest $request): ResponseInterface
    {
        $payload = $request->input('payload');

        if (!$payload) {
            return ResponseFactory::json(['error' => 'Missing payload'], 400);
        }

        try {
            $data = Crypt::decrypt($payload);
        } catch (\Exception $e) {
            return ResponseFactory::json(['error' => 'Invalid payload'], 400);
        }

        if (!isset($data['component']) || !isset($data['attributes'])) {
            return ResponseFactory::json(['error' => 'Invalid component data'], 400);
        }

        $componentName = $data['component'];
        $attributes = $data['attributes'];

        try {
            // Render the component using the View facade
            $html = View::renderComponent($componentName, $attributes);

            return ResponseFactory::create($html, 200, [
                'Content-Type' => 'text/html',
                'X-Plugs-Component' => $componentName
            ]);
        } catch (\Exception $e) {
            return ResponseFactory::json(['error' => 'Rendering error: ' . $e->getMessage()], 500);
        }
    }
}
