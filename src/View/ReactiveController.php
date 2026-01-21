<?php

declare(strict_types=1);

namespace Plugs\View;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Plugs\Http\ResponseFactory;
use RuntimeException;

class ReactiveController
{
    protected ViewEngine $viewEngine;

    public function __construct(ViewEngine $viewEngine)
    {
        $this->viewEngine = $viewEngine;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = $request->getParsedBody();

            // If parsed body is empty, try to read from stream (fallback)
            if (empty($data)) {
                $body = (string) $request->getBody();
                if (empty($body)) {
                    // Last resort for some environments
                    $body = file_get_contents('php://input');
                }

                if (!empty($body)) {
                    $json = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data = $json;
                    }
                }
            }

            if (!$data) {
                $headers = json_encode($request->getHeaders());
                error_log("Plugs Reactive Error: No data received or invalid JSON. Content-Type: " . $request->getHeaderLine('Content-Type') . " Headers: $headers");
                return ResponseFactory::json(['error' => 'No data received', 'debug_headers' => $request->getHeaders()], 400);
            }

            $componentName = $data['component'] ?? null;
            $action = $data['action'] ?? null;
            $state = $data['state'] ?? null;
            $params = $data['params'] ?? [];
            $id = $data['id'] ?? null;

            if (!$componentName || !$action || !$state) {
                error_log("Plugs Reactive Error: Missing fields. Component: $componentName, Action: $action, State: " . ($state ? 'Present' : 'Missing'));
                return ResponseFactory::json(['error' => 'Invalid request structure'], 400);
            }

            $className = "App\\Components\\" . $this->viewEngine->snakeToPascalCase(str_replace('.', '\\', $componentName));

            if (!class_exists($className)) {
                error_log("Plugs Reactive Error: Class not found: $className");
                return ResponseFactory::json(['error' => "Component class [{$className}] not found"], 404);
            }

            /** @var ReactiveComponent $component */
            $component = new $className($componentName);
            if ($id) {
                $component->setId($id);
            }
            $component->hydrate($state);

            if (!method_exists($component, $action)) {
                error_log("Plugs Reactive Error: Action $action not found in $className");
                return ResponseFactory::json(['error' => "Action [{$action}] not found on component"], 404);
            }

            // Call the action
            $component->$action(...$params);

            // Re-render
            $html = $this->viewEngine->render($componentName, array_merge($component->getState(), ['slot' => '']), true);

            return ResponseFactory::json([
                'html' => $html,
                'state' => $component->serializeState(),
                'id' => $component->getId()
            ]);
        } catch (\Throwable $e) {
            error_log("Plugs Reactive Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ResponseFactory::json(['error' => 'Internal Server Error', 'message' => $e->getMessage()], 500);
        }
    }
}
