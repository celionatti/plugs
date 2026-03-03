<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Profiler Dashboard Controller
 *
 * Provides routes for viewing and managing profiler data.
 */
class ProfilerController
{
    /**
     * Display profiler dashboard with profile list
     */
    public function index(ServerRequestInterface $request)
    {
        if (!config('app.debug', false)) {
            abort(403, 'Profiler is disabled in production.');
        }

        $profiles = Profiler::getProfiles(50);

        return $this->renderInternal('debug.profiler.index', ['profiles' => $profiles]);
    }

    /**
     * Display single profile detail
     */
    public function show(ServerRequestInterface $request, string $id)
    {
        if (!config('app.debug', false)) {
            abort(403, 'Profiler is disabled in production.');
        }

        $profile = Profiler::getProfile($id);
        if (!$profile) {
            abort(404, 'Profile not found');
        }

        return $this->renderInternal('debug.profiler.show', ['profile' => $profile]);
    }

    /**
     * Render an internal framework view
     */
    private function renderInternal(string $view, array $data = [])
    {
        $engine = new \Plugs\View\PlugViewEngine(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'View',
            config('app.paths.cache'),
            app(\Plugs\Container\Container::class),
            true
        );

        $viewInstance = new \Plugs\View\View($engine, $view, $data);

        return ResponseFactory::html($viewInstance->render());
    }

    /**
     * Delete a single profile
     */
    public function destroy(ServerRequestInterface $request, string $id)
    {
        $deleted = Profiler::deleteProfile($id);

        if ($deleted) {
            return ResponseFactory::json(['success' => true, 'message' => 'Profile deleted']);
        }

        return ResponseFactory::json(['success' => false, 'message' => 'Profile not found or could not be deleted'], 404);
    }

    /**
     * Clear all profiles
     */
    public function clear(ServerRequestInterface $request)
    {
        $count = Profiler::clearProfiles();

        return ResponseFactory::json([
            'success' => true,
            'message' => sprintf('Successfully cleared %d profiles', $count),
            'deleted' => $count,
        ]);
    }

    public function latest(ServerRequestInterface $request)
    {
        if (!config('app.debug', false)) {
            abort(403, 'Profiler is disabled in production.');
        }

        $profiles = Profiler::getProfiles(1);

        if (empty($profiles)) {
            return ResponseFactory::redirect('/plugs/profiler');
        }

        return ResponseFactory::redirect('/plugs/profiler/' . $profiles[0]['id']);
    }
}
