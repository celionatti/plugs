<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers\Debug;

use Plugs\Debug\Profiler;
use Plugs\Http\ResponseFactory;
use Plugs\View\ViewEngine;
use Plugs\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PerformanceController
{
    private $viewEngine;
    private $storagePath;

    public function __construct()
    {
        // Custom View Engine for Framework Views
        // Pointing to src/View/debug to allow framework-internal views
        $viewPath = __DIR__ . '/../../../View/debug';
        $cachePath = BASE_PATH . 'storage/framework/views';
        $this->viewEngine = new ViewEngine($viewPath, $cachePath, false);

        $this->storagePath = BASE_PATH . 'storage/framework/profiler/';
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $profiles = [];
        $files = glob($this->storagePath . '*.json');

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $profiles[] = $data;
            }
        }

        // Sort by timestamp desc
        usort($profiles, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $this->render('profiler.index', ['profiles' => $profiles]);
    }

    public function show(ServerRequestInterface $request, $id): ResponseInterface
    {
        $file = $this->storagePath . $id . '.json';

        if (!file_exists($file)) {
            return ResponseFactory::createResponse(404, 'Profile not found');
        }

        $profile = json_decode(file_get_contents($file), true);

        return $this->render('profiler.show', ['profile' => $profile]);
    }

    public function latest(ServerRequestInterface $request): ResponseInterface
    {
        $files = glob($this->storagePath . '*.json');
        if (empty($files)) {
            return ResponseFactory::createResponse(404, 'No profiles found');
        }

        // Sort by modification time descending
        array_multisort(array_map('filemtime', $files), SORT_DESC, $files);

        $latest = $files[0];
        $id = pathinfo($latest, PATHINFO_FILENAME);

        // Redirect to show
        // Assuming we have a helper or can return a redirect response
        // Using raw header for now as ResponseFactory might not have redirect helper ready or I'm lazy
        return ResponseFactory::createResponse(302)->withHeader('Location', "/debug/performance/{$id}");
    }

    private function render(string $viewName, array $data = []): ResponseInterface
    {
        $view = new View($this->viewEngine, $viewName, $data);
        $content = $view->render();

        $response = ResponseFactory::createResponse(200);
        $response->getBody()->write($content);

        return $response;
    }
}
