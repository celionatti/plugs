<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Http\Message\ServerRequest;
use Plugs\Http\ResponseFactory;
use Plugs\Facades\Crypt;
use Plugs\Facades\View;

class ComponentController
{
    public function render(ServerRequest $request)
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

        // Security check: Ensure we only render components
        // In a real app, you might want a whitelist or stricter check
        // For now, we rely on the encrypted payload originating from our server

        // Render the component
        // logic mirrors ViewCompiler's renderComponent but dynamically
        // We need to resolve the alias to a class or view

        // We'll use the View helper to render it. 
        // Note: Slot content from the original definition is NOT passed here currently
        // because serialization of arbitrary HTML slots is complex. 
        // Lazy components are best used for self-contained widgets.

        try {
            // We can use the blade-like renderComponent logic if available globally,
            // or just render the view/class directly.
            // Since we have the component name (e.g. 'User.Card'), we can try to render it.

            // Simplest approach for V1: Render as a dynamic component
            // But we need to convert 'User.Card' back to a format renderComponent understands or specific class.

            // Actually, View::make($componentName, $attributes) might work if it's a view-based component.
            // If it's a class component, we need the class resolution logic.

            // Let's defer to a helper method that mimics the compiler's runtime logic
            // For now, let's assume view-based or simple class components which View::make can often handle
            // OR use the <x-dynamic-component> logic if it existed.

            // A robust way is to use the existing component rendering mechanism.
            // In Plugs, $view->renderComponent($name, $data) is verified to work.

            // We need access to the View instance.
            $html = View::renderComponent($componentName, $attributes);

            return $html;

        } catch (\Exception $e) {
            return ResponseFactory::json(['error' => 'Rendering error: ' . $e->getMessage()], 500);
        }
    }
}
