
<div class="p-6 rounded-2xl bg-white border border-slate-100 shadow-sm">
    <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-4">System Info</h3>
    <div class="space-y-3">
        <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500">PHP Version</span>
            <span class="font-bold text-slate-800">{{ PHP_VERSION }}</span>
        </div>
        <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500">Server</span>
            <span class="font-bold text-slate-800">{{ $_SERVER["SERVER_SOFTWARE"] ?? "Unknown" }}</span>
        </div>
        <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500">Memory usage</span>
            <span class="font-bold text-slate-800">{{ round(memory_get_usage() / 1024 / 1024, 2) }} MB</span>
        </div>
    </div>
</div>
