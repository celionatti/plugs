@extends('admin::layouts.admin')

@section('title', 'System Logs')

@section('styles')
<style>
    .log-console {
        font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 0.75rem;
        padding: 1.5rem;
        font-size: 0.875rem;
        line-height: 1.6;
        min-height: 500px;
        max-height: 700px;
        overflow-y: auto;
        box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
    }
    .log-row {
        padding: 0.25rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        gap: 1rem;
        transition: background 0.2s;
    }
    .log-row:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    .log-time { color: #94a3b8; min-width: 160px; }
    .log-level { font-weight: bold; min-width: 80px; }
    .level-error { color: #f87171; }
    .level-warning { color: #fbbf24; }
    .level-info { color: #60a5fa; }
    .level-debug { color: #9ca3af; }
    .level-unknown { color: #4b5563; }
    .log-msg { color: #f1f5f9; white-space: pre-wrap; flex: 1; }
    
    .console-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .animate-pulse-slow {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .5; }
    }
</style>
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">System Logs</h1>
            <p class="text-slate-500 dark:text-slate-400">Monitor real-time system activities and debug logs.</p>
        </div>
        <div class="flex items-center gap-3">
            <form action="{{ route('admin.logs.clear') }}" method="POST" data-spa="true" onsubmit="return confirm('Are you sure you want to clear the logs?')">
                @csrf
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm font-bold hover:bg-rose-50 dark:hover:bg-rose-900/30 hover:text-rose-600 dark:hover:text-rose-400 transition-all active:scale-95 shadow-sm">
                    Clear Logs
                </button>
            </form>
            <button id="refreshBtn" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 transition-all active:scale-95 shadow-lg shadow-indigo-600/20 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" id="logSearch" placeholder="Search logs..." class="w-full pl-10 pr-4 py-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 transition-all outline-none">
            </div>
        </div>
        <div>
            <select id="levelFilter" class="w-full px-4 py-3 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Levels</option>
                <option value="ERROR">Error</option>
                <option value="WARNING">Warning</option>
                <option value="INFO">Info</option>
                <option value="DEBUG">Debug</option>
            </select>
        </div>
        <div class="flex items-center justify-end px-4">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="autoRefresh" class="sr-only peer" checked>
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                <span class="ml-3 text-sm font-medium text-slate-500">Auto-refresh</span>
            </label>
        </div>
    </div>

    <!-- Console Container -->
    <div class="relative animate-in fade-in zoom-in-95 duration-500">
        <div id="liveIndicator" class="absolute top-4 right-6 flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-emerald-500 z-10">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse-slow"></span>
            Live Monitoring
        </div>
        
        <div id="logConsole" class="log-console custom-scrollbar">
            <!-- Logs will be loaded here -->
            <div class="flex items-center justify-center h-full opacity-50">
                <div class="text-center">
                    <div class="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                    <p>Connecting to log stream...</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const consoleEl = document.getElementById('logConsole');
    const searchInput = document.getElementById('logSearch');
    const levelFilter = document.getElementById('levelFilter');
    const autoRefresh = document.getElementById('autoRefresh');
    const refreshBtn = document.getElementById('refreshBtn');
    const liveIndicator = document.getElementById('liveIndicator');
    
    let logsData = [];
    let refreshInterval = null;

    async function fetchLogs() {
        try {
            const response = await fetch('{{ route("admin.logs.api") }}');
            const result = await response.json();
            
            if (result.status === 'success') {
                logsData = result.data;
                renderLogs();
            }
        } catch (error) {
            console.error('Failed to fetch logs:', error);
        }
    }

    function renderLogs() {
        const searchTerm = searchInput.value.toLowerCase();
        const level = levelFilter.value;
        
        const filteredLogs = logsData.filter(log => {
            const matchesSearch = log.raw.toLowerCase().indexOf(searchTerm) !== -1;
            const matchesLevel = level === '' || log.level === level;
            return matchesSearch && matchesLevel;
        });

        if (filteredLogs.length === 0) {
            consoleEl.innerHTML = '<div class="flex items-center justify-center h-full opacity-50"><p>No logs found matching your criteria.</p></div>';
            return;
        }

        const html = filteredLogs.map(log => {
            const levelClass = 'level-' + log.level.toLowerCase();
            return `
                <div class="log-row">
                    <div class="log-time">[` + log.timestamp + `]</div>
                    <div class="log-level ` + levelClass + `">` + log.level + `</div>
                    <div class="log-msg">` + escapeHtml(log.message) + `</div>
                </div>
            `;
        }).join('');

        const isAtBottom = consoleEl.scrollHeight - consoleEl.scrollTop <= consoleEl.clientHeight + 100;
        
        consoleEl.innerHTML = html;

        if (isAtBottom) {
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function startAutoRefresh() {
        if (refreshInterval) clearInterval(refreshInterval);
        refreshInterval = setInterval(fetchLogs, 3000);
        liveIndicator.classList.remove('hidden');
    }

    function stopAutoRefresh() {
        if (refreshInterval) clearInterval(refreshInterval);
        liveIndicator.classList.add('hidden');
    }

    // Event Listeners
    searchInput.addEventListener('input', renderLogs);
    levelFilter.addEventListener('change', renderLogs);
    refreshBtn.addEventListener('click', fetchLogs);
    
    autoRefresh.addEventListener('change', (e) => {
        if (e.target.checked) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    // Initial load
    fetchLogs();
    startAutoRefresh();
</script>
@endsection
