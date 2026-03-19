<?php

declare(strict_types = 1)
;

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeThemeCommand extends Command
{
    protected string $description = 'Create a new theme scaffolding';

    public function handle(): int
    {
        $this->title('Theme Generator');

        $name = $this->argument('0') ?? $this->ask('Theme name', 'Carbon');
        $name = Str::kebab($name);

        $themePath = getcwd() . '/resources/views/themes/' . $name;

        if (Filesystem::exists($themePath)) {
            if (!$this->confirm("Theme '{$name}' already exists. Overwrite?", false)) {
                $this->warning('Operation cancelled.');
                return 0;
            }
        }

        $this->info("Creating theme '{$name}' at " . str_replace(getcwd(), '', $themePath));

        Filesystem::ensureDir($themePath);
        Filesystem::ensureDir($themePath . '/layouts');
        Filesystem::ensureDir($themePath . '/components');
        Filesystem::ensureDir($themePath . '/modules');

        // 1. theme.json
        $this->createThemeJson($themePath, $name);

        // 2. layouts/app.plug.php
        $this->createLayout($themePath, $name);

        // 3. welcome.plug.php
        $this->createWelcome($themePath, $name);

        $this->success("Theme '{$name}' created successfully!");

        $this->section('Next Steps');
        $this->bulletList([
            "Activate your theme in .env: APP_THEME={$name}",
            "Customize styles in resources/views/themes/{$name}/layouts/app.plug.php",
            "Override module views in resources/views/themes/{$name}/modules/",
        ]);

        return 0;
    }

    private function createThemeJson(string $path, string $name): void
    {
        $json = [
            'name' => Str::title($name),
            'description' => 'A beautiful new theme for Plugs.',
            'author' => 'Plugs User',
            'version' => '1.0.0',
            'tags' => ['modern', 'clean'],
        ];

        Filesystem::put($path . '/theme.json', json_encode($json, JSON_PRETTY_PRINT));
    }

    private function createLayout(string $path, string $name): void
    {
        $content = <<<'HTML'

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Plugs Theme' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="/css/global.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; }
        .glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="min-h-screen font-sans">
    <slot></slot>
</body>
</html>
HTML;
        Filesystem::put($path . '/layouts/app.plug.php', $content);
    }

    private function createWelcome(string $path, string $name): void
    {
        $content = <<<HTML

<layout name="layouts.app">
    <div class="flex flex-col items-center justify-center min-h-screen text-center px-4">
        <div class="glass p-12 rounded-3xl shadow-2xl max-w-2xl transform hover:scale-[1.02] transition-all">
            <h1 class="text-6xl font-black mb-6 bg-gradient-to-r from-blue-400 to-emerald-400 bg-clip-text text-transparent">
                {{ Str::title('$name') }} Theme
            </h1>
            <p class="text-xl text-slate-400 mb-10 leading-relaxed">
                Your new high-performance theme is ready for action.
            </p>
            <div class="flex gap-4 justify-center">
                <a href="/login" class="px-8 py-3 bg-blue-600 hover:bg-blue-500 rounded-xl font-bold transition-all shadow-lg shadow-blue-500/20">
                    Get Started
                </a>
                <a href="https://plugs.dev/docs" class="px-8 py-3 bg-white/5 hover:bg-white/10 rounded-xl font-bold transition-all border border-white/10">
                    Documentation
                </a>
            </div>
        </div>
    </div>
</layout>
HTML;
        Filesystem::put($path . '/welcome.plug.php', $content);
    }
}
