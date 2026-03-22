@extends('admin::layouts.admin')

@section('title', 'AI Prompt Center')

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">AI Prompt Center</h1>
    <p class="text-slate-500 dark:text-slate-400 mt-1 font-medium">Generate framework components using natural language — modules, themes, models, and more.</p>
</div>

<!-- AI Status Bar -->
<div id="statusBar" class="mb-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-700 p-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div id="statusDot" class="w-2.5 h-2.5 rounded-full bg-slate-300 animate-pulse"></div>
        <span id="statusText" class="text-sm font-medium text-slate-500 dark:text-slate-400">Checking AI configuration...</span>
    </div>
    <div id="statusBadge" class="hidden px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
    <!-- Prompt Input Area (Left Column) -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 sticky top-24">
            <h2 class="text-lg font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                New Prompt
            </h2>

            <form id="aiPromptForm" class="space-y-4">
                <div>
                    <label for="prompt" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">What do you want to build?</label>
                    <textarea id="prompt" name="prompt" rows="6"
                        class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all outline-none resize-none text-sm placeholder-slate-400 dark:text-white"
                        placeholder="e.g. Create a Blog module with posts, categories, and tags..."></textarea>
                </div>

                <button type="submit" id="generateBtn"
                    class="flex items-center justify-center gap-2 w-full py-3.5 rounded-2xl bg-black dark:bg-white text-white dark:text-black font-bold text-sm hover:scale-[1.02] active:scale-[0.98] transition-all shadow-lg shadow-black/10 dark:shadow-white/10 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
                    <svg id="btnIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <svg id="btnSpinner" class="hidden animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span id="btnText">Generate</span>
                </button>

                <!-- Quick Templates -->
                <div class="pt-4 border-t border-slate-50 dark:border-slate-800 mt-2">
                    <span class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Quick Templates</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="setPrompt('Create a new feature module named \'Blog\' with posts and categories functionality.')"
                            class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Blog Module</button>
                        <button type="button" onclick="setPrompt('Generate a modern minimalist theme called \'Elevate\' with large typography and lots of whitespace.')"
                            class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Minimal Theme</button>
                        <button type="button" onclick="setPrompt('Create a User model with name, email, password fields, and a Post model with title, content, and user_id foreign key.')"
                            class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Model Pair</button>
                        <button type="button" onclick="setPrompt('Create an API-only module named \'Products\' with full CRUD controller, model with validation, migration, and routes.')"
                            class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">API Module</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Area (Right Column) -->
    <div class="lg:col-span-3">
        <!-- Welcome State -->
        <div id="welcomeState" class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-12 flex flex-col items-center justify-center text-center min-h-[400px]">
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Ready to Scaffold</h3>
            <p class="text-slate-500 dark:text-slate-400 max-w-sm text-sm leading-relaxed">Describe what you want to build and the AI will generate the complete file structure, code, and boilerplate for you.</p>
        </div>

        <!-- Progress State -->
        <div id="progressState" class="hidden space-y-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white">Generating Components</h3>
                        <p class="text-xs text-slate-400">This may take a moment...</p>
                    </div>
                </div>

                <!-- Pipeline Steps -->
                <div class="space-y-3" id="pipelineSteps">
                    <div class="pipeline-step flex items-center gap-3 p-3 rounded-xl" data-step="validate">
                        <div class="step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-slate-100 dark:bg-slate-800">
                            <span class="step-number text-[10px] font-bold text-slate-400">1</span>
                        </div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">Validating prompt</span>
                    </div>
                    <div class="pipeline-step flex items-center gap-3 p-3 rounded-xl" data-step="connect">
                        <div class="step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-slate-100 dark:bg-slate-800">
                            <span class="step-number text-[10px] font-bold text-slate-400">2</span>
                        </div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">Connecting to AI provider</span>
                    </div>
                    <div class="pipeline-step flex items-center gap-3 p-3 rounded-xl" data-step="generate">
                        <div class="step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-slate-100 dark:bg-slate-800">
                            <span class="step-number text-[10px] font-bold text-slate-400">3</span>
                        </div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">Generating code with AI</span>
                    </div>
                    <div class="pipeline-step flex items-center gap-3 p-3 rounded-xl" data-step="parse">
                        <div class="step-icon w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 bg-slate-100 dark:bg-slate-800">
                            <span class="step-number text-[10px] font-bold text-slate-400">4</span>
                        </div>
                        <span class="text-sm text-slate-500 dark:text-slate-400">Parsing response</span>
                    </div>
                </div>
            </div>

            <!-- Console Log -->
            <div class="bg-slate-900 rounded-2xl border border-slate-700 overflow-hidden">
                <div class="px-4 py-2 border-b border-slate-700 flex items-center gap-2">
                    <div class="flex gap-1.5">
                        <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                    </div>
                    <span class="text-[10px] font-mono text-slate-500 ml-2">generation.log</span>
                </div>
                <div id="consoleLog" class="p-4 font-mono text-xs text-slate-400 max-h-[200px] overflow-y-auto space-y-1">
                </div>
            </div>
        </div>

        <!-- Success State -->
        <div id="successState" class="hidden space-y-4">
            <!-- Success Header -->
            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-6 rounded-3xl shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <svg class="w-20 h-20" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="relative z-10 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold">Generation Complete</h2>
                        <p id="successSummary" class="text-emerald-100 text-sm"></p>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div id="instructionsCard" class="hidden bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-2xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <h4 class="text-sm font-bold text-amber-800 dark:text-amber-300 mb-1">Setup Instructions</h4>
                        <p id="instructionText" class="text-sm text-amber-700 dark:text-amber-400 leading-relaxed"></p>
                    </div>
                </div>
            </div>

            <!-- Generated Files -->
            <div id="filesContainer" class="space-y-3"></div>

            <!-- Raw Response Toggle -->
            <div class="mt-4">
                <button id="toggleRaw" onclick="toggleRawResponse()" class="text-xs font-bold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                    Show raw AI output
                </button>
                <div id="rawResponse" class="hidden mt-3 bg-slate-900 text-slate-300 p-4 rounded-2xl border border-slate-700 font-mono text-xs whitespace-pre-wrap max-h-[400px] overflow-y-auto"></div>
            </div>
        </div>

        <!-- Error State -->
        <div id="errorState" class="hidden space-y-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-rose-200 dark:border-rose-900/30 overflow-hidden">
                <!-- Error Header -->
                <div class="bg-rose-50 dark:bg-rose-900/10 p-6 border-b border-rose-100 dark:border-rose-900/30">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-900/30 text-rose-500 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h4 id="errorTitle" class="font-bold text-rose-800 dark:text-rose-300 text-base">Generation Failed</h4>
                            <p id="errorMessage" class="text-sm text-rose-600 dark:text-rose-400 mt-1"></p>
                        </div>
                    </div>
                </div>
                <!-- Error Details -->
                <div class="p-6 space-y-4">
                    <div id="errorDetails" class="hidden">
                        <h5 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Error Details</h5>
                        <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 font-mono text-xs text-slate-600 dark:text-slate-400" id="errorDetailsText"></div>
                    </div>
                    <div id="errorSuggestion" class="hidden">
                        <h5 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">How to Fix</h5>
                        <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30 rounded-xl p-3 text-sm text-blue-700 dark:text-blue-400 flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                            <span id="errorSuggestionText"></span>
                        </div>
                    </div>
                    <button onclick="resetUI()" class="text-sm font-bold text-indigo-500 hover:text-indigo-600 transition-colors">← Try again</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- File Template -->
<template id="fileTemplate">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="p-3 px-5 border-b border-slate-50 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="text-xs font-bold text-slate-600 dark:text-slate-300 font-mono file-path"></span>
                <span class="file-status px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest hidden"></span>
            </div>
            <button class="flex items-center gap-1 text-[10px] font-bold text-indigo-500 hover:text-indigo-600 transition-colors uppercase tracking-widest copy-btn">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                Copy
            </button>
        </div>
        <div class="p-5">
            <pre class="text-xs font-mono text-slate-700 dark:text-slate-300 overflow-x-auto whitespace-pre-wrap leading-relaxed file-content"></pre>
        </div>
    </div>
</template>

@endsection

@section('scripts')
<style>
@verbatim
    .pipeline-step.active { background: rgba(99, 102, 241, 0.05); }
    .pipeline-step.active .step-icon { background: rgb(99, 102, 241); }
    .pipeline-step.active .step-number { color: white; }
    .pipeline-step.active span:last-child { color: rgb(99, 102, 241); font-weight: 600; }

    .pipeline-step.done .step-icon { background: rgb(16, 185, 129); }
    .pipeline-step.done .step-number { display: none; }
    .pipeline-step.done .step-icon::after { content: '✓'; color: white; font-size: 10px; font-weight: bold; }
    .pipeline-step.done span:last-child { color: rgb(16, 185, 129); }

    .pipeline-step.error .step-icon { background: rgb(244, 63, 94); }
    .pipeline-step.error .step-number { display: none; }
    .pipeline-step.error .step-icon::after { content: '✕'; color: white; font-size: 10px; font-weight: bold; }
    .pipeline-step.error span:last-child { color: rgb(244, 63, 94); font-weight: 600; }

    .console-entry { animation: fadeInUp 0.2s ease-out; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    #consoleLog::-webkit-scrollbar { width: 4px; }
    #consoleLog::-webkit-scrollbar-track { background: transparent; }
    #consoleLog::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }
@endverbatim
</style>

<script>
@verbatim
    // ========================================
    // State
    // ========================================
    let isGenerating = false;
    let aiStatus = null;

    // ========================================
    // DOM References
    // ========================================
    const $ = (id) => document.getElementById(id);

    function setPrompt(text) {
        $('prompt').value = text;
        $('prompt').focus();
    }

    function toggleRawResponse() {
        const raw = $('rawResponse');
        const btn = $('toggleRaw');
        const isHidden = raw.classList.contains('hidden');
        raw.classList.toggle('hidden');
        btn.innerHTML = isHidden
            ? '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg> Hide raw AI output'
            : '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg> Show raw AI output';
    }

    // ========================================
    // Console Logger
    // ========================================
    function logToConsole(message, type = 'info') {
        const log = $('consoleLog');
        const colors = {
            info: 'text-slate-400',
            success: 'text-emerald-400',
            error: 'text-rose-400',
            warn: 'text-amber-400',
            system: 'text-indigo-400',
        };
        const prefix = {
            info: '›',
            success: '✓',
            error: '✕',
            warn: '⚠',
            system: '⦿',
        };
        const time = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const entry = document.createElement('div');
        entry.className = `console-entry ${colors[type] || colors.info}`;
        entry.innerHTML = `<span class="text-slate-600">${time}</span> <span class="${colors[type]}">${prefix[type]}</span> ${message}`;
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    }

    // ========================================
    // Pipeline Steps
    // ========================================
    function setStepState(stepName, state) {
        const step = document.querySelector(`.pipeline-step[data-step="${stepName}"]`);
        if (!step) return;
        step.classList.remove('active', 'done', 'error');
        if (state) step.classList.add(state);
    }

    function resetPipeline() {
        document.querySelectorAll('.pipeline-step').forEach(s => s.classList.remove('active', 'done', 'error'));
        $('consoleLog').innerHTML = '';
    }

    // ========================================
    // UI State Management
    // ========================================
    function showState(stateName) {
        ['welcomeState', 'progressState', 'successState', 'errorState'].forEach(id => {
            $(id).classList.toggle('hidden', id !== stateName);
        });
    }

    function resetUI() {
        showState('welcomeState');
        resetPipeline();
        $('btnIcon').classList.remove('hidden');
        $('btnSpinner').classList.add('hidden');
        $('btnText').textContent = 'Generate';
        $('generateBtn').disabled = false;
        isGenerating = false;
    }

    function setLoading(loading) {
        isGenerating = loading;
        $('generateBtn').disabled = loading;
        $('btnIcon').classList.toggle('hidden', loading);
        $('btnSpinner').classList.toggle('hidden', !loading);
        $('btnText').textContent = loading ? 'Generating...' : 'Generate';
    }

    // ========================================
    // Status Check
    // ========================================
    async function checkAIStatus() {
        try {
            const res = await fetch('/admin/ai-prompt/status');
            aiStatus = await res.json();

            const dot = $('statusDot');
            const text = $('statusText');
            const badge = $('statusBadge');

            if (aiStatus.configured) {
                dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500';
                text.textContent = `Connected — ${aiStatus.driver} / ${aiStatus.model}`;
                text.className = 'text-sm font-medium text-emerald-600 dark:text-emerald-400';
                badge.textContent = 'Ready';
                badge.className = 'px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400';
            } else {
                dot.className = 'w-2.5 h-2.5 rounded-full bg-rose-500';
                text.textContent = 'No AI provider configured — add an API key to your .env file';
                text.className = 'text-sm font-medium text-rose-600 dark:text-rose-400';
                badge.textContent = 'Not configured';
                badge.className = 'px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400';
            }
            badge.classList.remove('hidden');
        } catch (e) {
            $('statusDot').className = 'w-2.5 h-2.5 rounded-full bg-amber-500';
            $('statusText').textContent = 'Could not check AI status';
            $('statusText').className = 'text-sm font-medium text-amber-600 dark:text-amber-400';
        }
    }

    // ========================================
    // Error Display
    // ========================================
    function showError(data) {
        showState('errorState');

        const typeLabels = {
            validation: 'Validation Error',
            config: 'Configuration Error',
            template: 'Template Error',
            api: 'AI Provider Error',
            empty_response: 'Empty Response',
        };

        $('errorTitle').textContent = typeLabels[data.error_type] || 'Generation Failed';
        $('errorMessage').textContent = data.error || data.message || 'An unknown error occurred.';

        if (data.details) {
            $('errorDetails').classList.remove('hidden');
            $('errorDetailsText').textContent = data.details;
        } else {
            $('errorDetails').classList.add('hidden');
        }

        if (data.suggestion) {
            $('errorSuggestion').classList.remove('hidden');
            $('errorSuggestionText').textContent = data.suggestion;
        } else {
            $('errorSuggestion').classList.add('hidden');
        }
    }

    // ========================================
    // Success Display
    // ========================================
    function showSuccess(data) {
        showState('successState');

        const filesContainer = $('filesContainer');
        filesContainer.innerHTML = '';

        if (data.parsed && data.result && data.result.files) {
            const files = data.result.files;
            $('successSummary').textContent = `Generated ${files.length} file${files.length !== 1 ? 's' : ''} ready to use.`;

            if (data.result.instructions) {
                $('instructionsCard').classList.remove('hidden');
                $('instructionText').textContent = data.result.instructions;
            } else {
                $('instructionsCard').classList.add('hidden');
            }

            files.forEach((file, i) => {
                const template = $('fileTemplate').content.cloneNode(true);
                template.querySelector('.file-path').textContent = file.path;
                template.querySelector('.file-content').textContent = file.content;

                const statusBadge = template.querySelector('.file-status');
                if (data.created_files && data.created_files.includes(file.path)) {
                    statusBadge.textContent = 'Created';
                    statusBadge.className = 'file-status px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400';
                } else if (data.failed_files && data.failed_files.some(f => f.path === file.path)) {
                    const failReason = data.failed_files.find(f => f.path === file.path).error;
                    statusBadge.textContent = 'Failed: ' + failReason;
                    statusBadge.className = 'file-status px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400';
                } else {
                    statusBadge.textContent = 'Schema Only';
                    statusBadge.className = 'file-status px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-slate-100 dark:bg-slate-800 text-slate-500';
                }

                const copyBtn = template.querySelector('.copy-btn');
                copyBtn.addEventListener('click', function() {
                    navigator.clipboard.writeText(file.content);
                    this.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Copied!';
                    setTimeout(() => {
                        this.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg> Copy';
                    }, 2000);
                });

                filesContainer.appendChild(template);
            });
        } else {
            // Non-structured response
            $('successSummary').textContent = 'AI returned a response (non-structured).';
            $('instructionsCard').classList.add('hidden');

            const div = document.createElement('div');
            div.className = 'bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-5';
            div.innerHTML = `<pre class="text-xs font-mono text-slate-700 dark:text-slate-300 overflow-x-auto whitespace-pre-wrap leading-relaxed">${escapeHtml(data.raw)}</pre>`;
            filesContainer.appendChild(div);
        }

        // Raw response
        $('rawResponse').textContent = data.raw || '';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========================================
    // Main Generation Flow
    // ========================================
    $('aiPromptForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isGenerating) return;

        const prompt = $('prompt').value.trim();
        if (!prompt) return;

        setLoading(true);
        showState('progressState');
        resetPipeline();

        // Step 1: Validate
        setStepState('validate', 'active');
        logToConsole('Starting generation pipeline...', 'system');
        logToConsole(`Prompt: "${prompt.substring(0, 80)}${prompt.length > 80 ? '...' : ''}"`, 'info');
        await sleep(300);
        setStepState('validate', 'done');
        logToConsole('Prompt validated', 'success');

        // Step 2: Connect
        setStepState('connect', 'active');
        const driverName = aiStatus?.driver || 'AI provider';
        const modelName = aiStatus?.model || 'default model';
        logToConsole(`Connecting to ${driverName} (${modelName})...`, 'info');
        await sleep(400);

        if (aiStatus && !aiStatus.configured) {
            setStepState('connect', 'error');
            logToConsole('No AI provider configured!', 'error');
            showError({
                error: 'No AI provider is configured.',
                error_type: 'config',
                details: 'No API key found for any AI provider.',
                suggestion: 'Add an API key to your .env file. For example: GROQ_API_KEY=your_key_here or OPENAI_API_KEY=your_key_here',
            });
            setLoading(false);
            return;
        }

        setStepState('connect', 'done');
        logToConsole(`Connected to ${driverName}`, 'success');

        // Step 3: Generate
        setStepState('generate', 'active');
        logToConsole('Sending prompt to AI model...', 'info');
        logToConsole('Waiting for response (this can take 10-30 seconds)...', 'warn');

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const headers = { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (csrfMeta) headers['X-CSRF-TOKEN'] = csrfMeta.content;

            const response = await fetch('/admin/ai-prompt/generate', {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({ prompt }),
            });

            const data = await response.json();

            if (!response.ok || data.error || data.message) {
                setStepState('generate', 'error');
                const errMsg = data.error || data.message || 'Unknown error';
                logToConsole(`Error: ${errMsg}`, 'error');
                if (data.details) logToConsole(`Details: ${data.details}`, 'error');

                showError(data);
                setLoading(false);
                return;
            }

            setStepState('generate', 'done');
            logToConsole('AI response received', 'success');

            // Step 4: Parse
            setStepState('parse', 'active');
            logToConsole('Parsing AI output...', 'info');
            await sleep(300);

            if (data.parsed && data.result?.files) {
                logToConsole(`Parsed ${data.result.files.length} file(s) from response`, 'success');
            } else {
                logToConsole('Response is not structured JSON — displaying raw output', 'warn');
            }

            setStepState('parse', 'done');
            logToConsole('Generation complete!', 'success');

            await sleep(300);
            showSuccess(data);

        } catch (error) {
            setStepState('generate', 'error');
            logToConsole(`Network error: ${error.message}`, 'error');

            showError({
                error: 'Network request failed.',
                error_type: 'api',
                details: error.message,
                suggestion: 'Check your internet connection and ensure the server is running. Also check the browser console for CORS or other network errors.',
            });
        }

        setLoading(false);
    });

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // ========================================
    // Init
    // ========================================
    checkAIStatus();
@endverbatim
</script>
@endsection
