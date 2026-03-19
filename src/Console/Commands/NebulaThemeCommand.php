<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class NebulaThemeCommand extends Command
{
    protected string $description = 'Scaffold the premium Nebula (Space) theme';

    public function handle(): int
    {
        $this->title('Nebula Theme Installer');

        $themePath = getcwd() . '/resources/views/themes/nebula';

        if (Filesystem::exists($themePath)) {
            if (!$this->confirm("Nebula theme already exists. Overwrite?", false)) {
                $this->warning('Operation cancelled.');
                return 0;
            }
        }

        $this->info("Installing Nebula theme...");

        Filesystem::ensureDir($themePath);
        Filesystem::ensureDir($themePath . '/layouts');
        Filesystem::ensureDir($themePath . '/components');
        Filesystem::ensureDir($themePath . '/modules/auth');

        // 1. theme.json
        $this->createThemeJson($themePath);

        // 2. layouts/app.plug.php
        $this->createLayout($themePath);

        // 3. welcome.plug.php
        $this->createWelcome($themePath);
        
        // 4. dashboard.plug.php
        $this->createDashboard($themePath);

        // 5. Auth Overrides
        $this->createAuthOverrides($themePath);

        $this->success("Nebula theme installed successfully!");
        
        $this->section('Activation');
        $this->info("Set APP_THEME=nebula in your .env file to activate.");

        return 0;
    }

    private function createThemeJson(string $path): void
    {
        $json = [
            'name' => 'Nebula',
            'description' => 'A premium space-themed aesthetic with glassmorphism and ambient effects.',
            'author' => 'Plugs Core',
            'version' => '2.0.0',
            'tags' => ['space', 'premium', 'glassmorphism', 'dark'],
        ];

        Filesystem::put($path . '/theme.json', json_encode($json, JSON_PRETTY_PRINT));
    }

    private function createLayout(string $path): void
    {
        // I'll use the layout we built earlier
        $content = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Plugs' }} | Nebula Core</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Space+Grotesk:wght@300;500;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'outfit': ['Outfit', 'sans-serif'],
                        'space': ['Space Grotesk', 'sans-serif'],
                    },
                    colors: {
                        'nebula': {
                            'deep': '#020617',
                            'void': '#030712',
                            'glow': '#4f46e5',
                            'accent': '#9333ea',
                        }
                    },
                    backgroundImage: {
                        'gradient-nebula': 'linear-gradient(135deg, #4f46e5 0%, #9333ea 100%)',
                        'gradient-void': 'radial-gradient(circle at center, #1e1b4b 0%, #020617 100%)',
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #020617; color: #f8fafc; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
        .glass-panel { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.8); }
        .text-gradient-nebula { background: linear-gradient(135deg, #818cf8 0%, #c084fc 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nebula-grid { background-image: radial-gradient(rgba(79, 70, 229, 0.1) 1px, transparent 1px); background-size: 40px 40px; }
        .ambient-orb { position: absolute; border-radius: 50%; filter: blur(100px); z-index: -1; animation: float 20s infinite alternate; }
        @keyframes float { 0% { transform: translate(0, 0); } 100% { transform: translate(100px, 100px); } }
    </style>
</head>
<body class="min-h-screen relative nebula-grid">
    <div class="ambient-orb w-[500px] h-[500px] bg-indigo-900/20 -top-20 -left-20"></div>
    <div class="ambient-orb w-[400px] h-[400px] bg-purple-900/20 bottom-0 right-0" style="animation-delay: -5s;"></div>
    
    <yield:content />
</body>
</html>
HTML;
        Filesystem::put($path . '/layouts/app.plug.php', $content);
    }

    private function createWelcome(string $path): void
    {
        $content = <<<'HTML'
<layout name="layouts.app">
    <div class="flex flex-col items-center justify-center min-h-screen text-center px-4 relative z-10">
        <div class="glass-panel p-16 rounded-[2.5rem] shadow-2xl max-w-3xl transform hover:scale-[1.01] transition-all duration-700">
            <div class="inline-block px-4 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-bold tracking-widest uppercase mb-6">
                Nebula Core v2.0 Activated
            </div>
            <h1 class="text-7xl font-black mb-6 font-space tracking-tight leading-tight">
                Ignite Your <span class="text-gradient-nebula">PHP Potential</span>
            </h1>
            <p class="text-xl text-slate-400 mb-12 font-light leading-relaxed max-w-xl mx-auto">
                The high-performance framework designed for the next frontier of web evolution.
            </p>
            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                <a href="/login" class="px-10 py-4 bg-gradient-nebula hover:shadow-[0_0_40px_rgba(79,70,229,0.5)] rounded-2xl font-bold transition-all text-lg shadow-xl shadow-indigo-500/20">
                    Initialize System
                </a>
                <a href="/register" class="px-10 py-4 glass-panel hover:bg-white/5 rounded-2xl font-bold transition-all text-lg border border-white/10">
                    Join Ecosystem
                </a>
            </div>
        </div>
    </div>
</layout>
HTML;
        Filesystem::put($path . '/welcome.plug.php', $content);
    }

    private function createDashboard(string $path): void
    {
        $content = <<<'HTML'
<layout name="layouts.app">
    <div class="min-h-screen p-8 relative z-10">
        <header class="flex justify-between items-center mb-12 glass-panel p-6 rounded-2xl">
            <h1 class="text-2xl font-black font-space tracking-tighter"><span class="text-gradient-nebula">COMMAND</span> CENTER</h1>
            <div class="flex items-center gap-4">
                <span class="text-xs font-bold text-gray-400">STATUS: ON-LINE</span>
                <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_10px_#10b981]"></div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 glass-panel p-10 rounded-3xl min-h-[400px]">
                <h2 class="text-3xl font-bold mb-8 font-space">System <span class="text-indigo-400">Telemetry</span></h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="p-6 rounded-2xl bg-white/5 border border-white/10">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Identity Profile</p>
                        <p class="text-xl font-bold text-white">{{ $user->name }}</p>
                    </div>
                    <div class="p-6 rounded-2xl bg-white/5 border border-white/10">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Access Tier</p>
                        <p class="text-xl font-bold text-indigo-400">{{ $user->role }}</p>
                    </div>
                </div>
            </div>
            
            <div class="glass-panel p-10 rounded-3xl">
                <h2 class="text-xl font-bold mb-6 font-space">Diagnostics</h2>
                <div class="space-y-4">
                    <div class="flex justify-between py-2 border-b border-white/5">
                        <span class="text-sm text-gray-400">Core Sync</span>
                        <span class="text-sm font-bold text-emerald-400">ENABLED</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-white/5">
                        <span class="text-sm text-gray-400">Memory Load</span>
                        <span class="text-sm font-bold text-indigo-400">LOW</span>
                    </div>
                </div>
                <div class="mt-10">
                    <form action="/logout" method="POST">
                        @csrf
                        <button type="submit" class="w-full py-4 rounded-2xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 font-bold border border-rose-500/20 transition-all">TERMINATE LINK</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</layout>
HTML;
        Filesystem::put($path . '/dashboard.plug.php', $content);
    }

    private function createAuthOverrides(string $path): void
    {
        $loginContent = <<<'HTML'
<layout name="layouts.app">
    <div class="min-h-screen flex items-center justify-center p-8 relative z-10">
        <div class="max-w-md w-full glass-panel p-10 rounded-3xl">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-black font-space tracking-tight">Access <span class="text-gradient-nebula">Terminal</span></h2>
                <p class="text-gray-400 mt-2">Initialize secure link to core.</p>
            </div>
            <form action="/login" method="POST" class="space-y-6">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Signature</label>
                    <input name="email" type="email" required class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Fragment</label>
                    <input name="password" type="password" required class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                </div>
                <button type="submit" class="w-full py-4 bg-gradient-nebula rounded-xl font-bold text-white shadow-xl shadow-indigo-500/20 hover:scale-[1.02] transition-all">Confirm Access</button>
            </form>
        </div>
    </div>
</layout>
HTML;
        Filesystem::put($path . '/modules/auth/login.plug.php', $loginContent);

        $registerContent = <<<'HTML'
<layout name="layouts.app">
    <div class="min-h-screen flex items-center justify-center p-8 relative z-10">
        <div class="max-w-md w-full glass-panel p-10 rounded-3xl">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-black font-space tracking-tight">Identity <span class="text-gradient-nebula">Genesis</span></h2>
                <p class="text-gray-400 mt-2">Establish new presence in the void.</p>
            </div>
            <form action="/register" method="POST" class="space-y-6">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Full Entity Name</label>
                    <input name="name" type="text" required class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Comm Channel</label>
                    <input name="email" type="email" required class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Fragment</label>
                        <input name="password" type="password" required class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Confirm</label>
                        <input name="password_confirmation" type="password" required class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                    </div>
                </div>
                <button type="submit" class="w-full py-4 bg-gradient-nebula rounded-xl font-bold text-white shadow-xl shadow-indigo-500/20 hover:scale-[1.02] transition-all">Finalize Genesis</button>
            </form>
        </div>
    </div>
</layout>
HTML;
        Filesystem::put($path . '/modules/auth/register.plug.php', $registerContent);
    }
}
