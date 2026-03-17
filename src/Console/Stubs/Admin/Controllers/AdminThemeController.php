<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Plugs\View\ThemeManager;
use Plugs\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AdminThemeController
{
    protected ThemeManager $themeManager;

    public function __construct()
    {
        $this->themeManager = Container::getInstance()->make(ThemeManager::class);
    }

    /**
     * Display all available themes.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $themes      = $this->themeManager->getAvailableThemes();
        $activeTheme = $this->themeManager->getActiveTheme();

        return response(view('admin::themes.index', [
            'title'       => 'Themes',
            'themes'      => $themes,
            'activeTheme' => $activeTheme,
        ]));
    }

    /**
     * Activate a theme.
     */
    public function activate(ServerRequestInterface $request, string $name): ResponseInterface
    {
        try {
            $this->themeManager->activateTheme($name);

            // Clear the view cache so the new theme takes effect immediately
            try {
                $engine = Container::getInstance()->make('view');
                if ($engine instanceof \Plugs\View\PlugViewEngine) {
                    $engine->clearCache();
                }
            } catch (\Throwable) {
                // Non-critical
            }

            return ResponseFactory::redirect('/admin/themes')
                ->with('success', "Theme \"{$name}\" activated successfully.");
        } catch (\InvalidArgumentException $e) {
            return ResponseFactory::redirect('/admin/themes')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Serve a theme's screenshot.
     */
    public function screenshot(ServerRequestInterface $request, string $name): ResponseInterface
    {
        $info = $this->themeManager->getThemeInfo($name);

        if (!$info || empty($info['screenshot']) || !file_exists($info['screenshot'])) {
            return ResponseFactory::notFound('Screenshot not found');
        }

        return ResponseFactory::file($info['screenshot'], "{$name}-screenshot.png");
    }
}
