@extends('admin::layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="mb-10">
    <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">System Configuration</h1>
    <p class="text-slate-500 mt-1 font-medium">Fine-tune your platform's behavior, appearance, and security.</p>
</div>

<div class="max-w-5xl">
    <!-- Tabs Navigation -->
    <div class="flex items-center gap-1 bg-slate-100 p-1.5 rounded-2xl mb-8 w-fit shadow-inner" id="settings-tabs">
        <button type="button" onclick="switchTab('general')" id="tab-general" class="px-6 py-2.5 rounded-xl text-sm font-bold transition-all bg-white text-indigo-600 shadow-sm">
            General
        </button>
        <button type="button" onclick="switchTab('appearance')" id="tab-appearance" class="px-6 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-500 hover:text-slate-700">
            Appearance
        </button>
        <button type="button" onclick="switchTab('security')" id="tab-security" class="px-6 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-500 hover:text-slate-700">
            Security
        </button>
        <button type="button" onclick="switchTab('seo')" id="tab-seo" class="px-6 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-500 hover:text-slate-700">
            SEO & Analytics
        </button>
    </div>

    <form action="/admin/settings" method="POST">
        @csrf
        
        <div class="space-y-8">
            <!-- General Settings -->
            <div id="panel-general" class="settings-panel bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-50 bg-slate-50/30">
                    <h2 class="text-xl font-bold text-slate-800">Identity & Contact</h2>
                    <p class="text-sm text-slate-500 mt-1">Basic information about your platform.</p>
                </div>
                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Site Name</label>
                            <input type="text" name="site_name" value="{{ $settings['site_name'] }}" class="w-full px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 ml-1">Admin Email Address</label>
                            <input type="email" name="admin_email" value="{{ $settings['admin_email'] }}" class="w-full px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-slate-700 ml-1">Site Description</label>
                        <textarea name="site_description" rows="3" class="w-full px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800 resize-none">{{ $settings['site_description'] }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Appearance Settings -->
            <div id="panel-appearance" class="settings-panel bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden" style="display:none;">
                <div class="p-8 border-b border-slate-50 bg-slate-50/30">
                    <h2 class="text-xl font-bold text-slate-800">Visual Identity</h2>
                    <p class="text-sm text-slate-500 mt-1">Customize the look and feel of your admin panel.</p>
                </div>
                <div class="p-8 space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-bold text-slate-700 ml-1">Primary Theme Color</label>
                                <div class="flex gap-4 mt-2">
                                    <input type="color" name="primary_color" id="primary_color" value="{{ $settings['primary_color'] ?? '#6366f1' }}" class="w-14 h-14 rounded-2xl border-none cursor-pointer shadow-sm" oninput="updateThemePreview('primary', this.value)">
                                    <input type="text" id="primary_color_hex" value="{{ $settings['primary_color'] ?? '#6366f1' }}" readonly class="flex-1 px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 font-mono text-sm text-slate-600">
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-700 ml-1">Secondary (Accent) Color</label>
                                <div class="flex gap-4 mt-2">
                                    <input type="color" name="secondary_color" id="secondary_color" value="{{ $settings['secondary_color'] ?? '#4f46e5' }}" class="w-14 h-14 rounded-2xl border-none cursor-pointer shadow-sm" oninput="updateThemePreview('secondary', this.value)">
                                    <input type="text" id="secondary_color_hex" value="{{ $settings['secondary_color'] ?? '#4f46e5' }}" readonly class="flex-1 px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 font-mono text-sm text-slate-600">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-slate-700 ml-1">Interface Border Radius</label>
                                <select name="border_radius" id="border_radius" class="w-full px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800" onchange="updateThemePreview('radius', this.value)">
                                    <option value="0px" {{ (\Modules\Admin\Models\Setting::getValue('border_radius') === '0px') ? 'selected' : '' }}>None (Square)</option>
                                    <option value="0.5rem" {{ (\Modules\Admin\Models\Setting::getValue('border_radius') === '0.5rem') ? 'selected' : '' }}>Small (8px)</option>
                                    <option value="1rem" {{ (\Modules\Admin\Models\Setting::getValue('border_radius') === '1rem') ? 'selected' : '' }}>Medium (16px)</option>
                                    <option value="1.5rem" {{ (\Modules\Admin\Models\Setting::getValue('border_radius') === '1.5rem' || !\Modules\Admin\Models\Setting::getValue('border_radius')) ? 'selected' : '' }}>Large (24px)</option>
                                    <option value="2rem" {{ (\Modules\Admin\Models\Setting::getValue('border_radius') === '2rem') ? 'selected' : '' }}>Extra Large (32px)</option>
                                </select>
                            </div>

                            <div class="p-6 rounded-3xl bg-slate-50 border border-slate-100 space-y-4">
                                <label class="flex items-center justify-between cursor-pointer group">
                                    <span class="text-sm font-bold text-slate-700 group-hover:text-primary transition-colors">Force Admin Dark Mode</span>
                                    <div class="relative">
                                        <input type="checkbox" name="dark_mode" value="true" {{ $settings['dark_mode'] === 'true' ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                    </div>
                                </label>
                                <p class="text-xs text-slate-400 font-medium leading-relaxed">Overrides the system and session preference to always show the admin panel in dark mode.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function updateThemePreview(type, value) {
                    if (type === 'primary') {
                        document.documentElement.style.setProperty('--primary-color', value);
                        document.getElementById('primary_color_hex').value = value;
                    } else if (type === 'secondary') {
                        document.documentElement.style.setProperty('--secondary-color', value);
                        document.getElementById('secondary_color_hex').value = value;
                    } else if (type === 'radius') {
                        document.documentElement.style.setProperty('--border-radius', value);
                    }
                }
            </script>

            <!-- Security Settings -->
            <div id="panel-security" class="settings-panel bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden" style="display:none;">
                <div class="p-8 border-b border-slate-50 bg-slate-50/30">
                    <h2 class="text-xl font-bold text-slate-800">Access & Protection</h2>
                    <p class="text-sm text-slate-500 mt-1">Control who can join and how they authenticate.</p>
                </div>
                <div class="p-8 space-y-6">
                    <div class="space-y-4">
                        <label class="flex items-center gap-3 cursor-pointer group p-4 rounded-2xl border border-slate-100 hover:border-indigo-100 transition-all">
                            <input type="checkbox" name="registration_enabled" value="true" {{ $settings['registration_enabled'] === 'true' ? 'checked' : '' }} class="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="block text-sm font-bold text-slate-800">Allow Public Registration</span>
                                <span class="text-xs text-slate-400 font-medium">Allow anyone to create an account on your site.</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer group p-4 rounded-2xl border border-slate-100 hover:border-indigo-100 transition-all">
                            <input type="checkbox" name="two_factor_auth" value="true" {{ $settings['two_factor_auth'] === 'true' ? 'checked' : '' }} class="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="block text-sm font-bold text-slate-800">Enforce Two-Factor Authentication</span>
                                <span class="text-xs text-slate-400 font-medium">Require all users to verify their login with another device.</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- SEO Settings -->
            <div id="panel-seo" class="settings-panel bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden" style="display:none;">
                <div class="p-8 border-b border-slate-50 bg-slate-50/30">
                    <h2 class="text-xl font-bold text-slate-800">Traffic & Discovery</h2>
                    <p class="text-sm text-slate-500 mt-1">Optimize how search engines index your platform.</p>
                </div>
                <div class="p-8 space-y-6">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-slate-700 ml-1">Meta Keywords</label>
                        <input type="text" name="meta_keywords" value="{{ $settings['meta_keywords'] }}" placeholder="Separate with commas..." class="w-full px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-slate-700 ml-1">Google Analytics ID (G-XXXXXX)</label>
                        <input type="text" name="google_analytics_id" value="{{ $settings['google_analytics_id'] }}" class="w-full px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all font-medium text-slate-800 font-mono">
                    </div>
                </div>
            </div>

            <!-- Save Changes Bar -->
            <div class="flex items-center justify-end gap-4 pb-20 pt-4">
                <button type="reset" class="px-8 py-4 rounded-2xl bg-white border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all active:scale-95">
                    Discard Changes
                </button>
                <button type="submit" class="px-10 py-4 rounded-2xl gradient-bg text-white font-bold shadow-xl shadow-indigo-500/20 hover:shadow-indigo-500/40 hover:-translate-y-0.5 transition-all active:scale-95 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Apply Configurations
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function switchTab(name) {
    document.querySelectorAll('.settings-panel').forEach(function(p) { p.style.display = 'none'; });
    document.querySelectorAll('#settings-tabs button').forEach(function(b) {
        b.className = 'px-6 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-500 hover:text-slate-700';
    });
    document.getElementById('panel-' + name).style.display = 'block';
    var activeBtn = document.getElementById('tab-' + name);
    activeBtn.className = 'px-6 py-2.5 rounded-xl text-sm font-bold transition-all bg-white text-indigo-600 shadow-sm';
}
</script>
@endsection
