<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Utils\Minifier;

class MakeSpaAssetCommand extends Command
{
    protected string $description = 'Create the plugs-spa.js file in public plugs directory with optional minification';

    public function handle(): int
    {
        $this->title('SPA Asset Generator');

        $directory = getcwd() . '/public/plugs';
        $filename = 'plugs-spa.js';
        $path = $directory . '/' . $filename;
        $shouldMinify = $this->hasOption('min');
        $force = $this->isForce();

        // Ensure directory exists
        if (!$this->ensureDirectory($directory)) {
            $this->error("Failed to create directory: {$directory}");

            return 1;
        }

        // Check if file exists
        if ($this->fileExists($path) && !$force) {
            if (!$this->confirm("File {$filename} already exists. Overwrite?", false)) {
                $this->warning("Operation cancelled.");

                return 0;
            }
        }

        $this->section('Generating Asset');

        $content = $this->getJsContent();

        // Create standard file
        $this->task("Creating {$filename}", function () use ($path, $content) {
            file_put_contents($path, $content);
        });

        $this->success("Created: public/plugs/{$filename}");

        // Create minified file if requested
        if ($shouldMinify) {
            $minFilename = 'plugs-spa.min.js';
            $minPath = $directory . '/' . $minFilename;

            $this->task("Creating {$minFilename}", function () use ($minPath, $content) {
                $minified = $this->minify($content);
                file_put_contents($minPath, $minified);
            });

            $this->success("Created: public/plugs/{$minFilename}");
        }

        $this->newLine();
        $this->info("SPA Bridge installed successfully.");
        $this->info("Add the following script to your layout head:");
        $this->line('<script src="/plugs/' . ($shouldMinify ? 'plugs-spa.min.js' : 'plugs-spa.js') . '"></script>');
        $this->newLine();

        return 0;
    }

    protected function defineOptions(): array
    {
        return [
            '--min' => 'Create a minified version (plugs-spa.min.js)',
            '--force' => 'Overwrite existing files',
        ];
    }

    /**
     * Minify JavaScript content
     *
     * @param string $js
     * @return string
     */
    private function minify(string $js): string
    {
        return Minifier::js($js);
    }

    private function getJsContent(): string
    {
        return <<<'JS'
class PlugsSPA {
    constructor(options = {}) {
        this.options = {
            contentSelector: options.contentSelector || '#app-content',
            loaderClass: options.loaderClass || 'spa-loading',
            onNavigate: options.onNavigate || (() => { }),
            onComplete: options.onComplete || (() => { }),
            onError: options.onError || ((err) => console.error('SPA Navigation Error:', err)),
            prefetch: options.prefetch !== false,
            viewportPrefetch: options.viewportPrefetch !== false,
            cacheMaxSize: options.cacheMaxSize || 50,
            cacheTTL: options.cacheTTL || 300000,
            persistCache: options.persistCache !== false
        };

        this.cache = new Map();
        this.cacheTimestamps = new Map();
        this.currentView = null;
        this.prefetchObserver = null;

        this.loadPersistentCache();
        this.init();
    }

    loadPersistentCache() {
        if (!this.options.persistCache) return;
        try {
            const stored = sessionStorage.getItem('plugs_spa_cache');
            if (stored) {
                const { cache, timestamps } = JSON.parse(stored);
                const now = Date.now();
                Object.entries(cache).forEach(([url, html]) => {
                    if (now - timestamps[url] < this.options.cacheTTL) {
                        this.cache.set(url, html);
                        this.cacheTimestamps.set(url, timestamps[url]);
                    }
                });
            }
        } catch (e) {
            console.warn('SPA: Persistent cache load failed', e);
        }
    }

    savePersistentCache() {
        if (!this.options.persistCache) return;
        try {
            const data = {
                cache: Object.fromEntries(this.cache),
                timestamps: Object.fromEntries(this.cacheTimestamps)
            };
            sessionStorage.setItem('plugs_spa_cache', JSON.stringify(data));
        } catch (e) {
            if (e.name === 'QuotaExceededError') {
                // If storage is full, clear half the cache and try again or just stop persisting
                const entries = Array.from(this.cache.keys());
                for (let i = 0; i < entries.length / 2; i++) {
                    this.cache.delete(entries[i]);
                    this.cacheTimestamps.delete(entries[i]);
                }
                console.warn('SPA: Storage quota exceeded, halved cache.');
            } else {
                console.warn('SPA: Persistent cache save failed', e);
            }
        }
    }

    cleanCache() {
        if (this.cache.size <= this.options.cacheMaxSize) return;

        const entries = Array.from(this.cacheTimestamps.entries())
            .sort((a, b) => a[1] - b[1]);

        while (this.cache.size > this.options.cacheMaxSize) {
            const [oldestUrl] = entries.shift();
            this.cache.delete(oldestUrl);
            this.cacheTimestamps.delete(oldestUrl);
        }
        this.savePersistentCache();
    }

    view(controller) {
        if (typeof controller === 'function') {
            const cleanup = controller();
            if (typeof cleanup === 'function') {
                this.currentView = { unmount: cleanup };
            } else {
                this.currentView = null;
            }
        } else if (controller && typeof controller === 'object') {
            this.currentView = controller;
            if (controller.mount) controller.mount();
        }
    }

    init() {
        if (window.plugsSPAInitialized) return;

        this.progressBar = document.createElement('div');
        this.progressBar.id = 'spa-progress-bar';
        Object.assign(this.progressBar.style, {
            position: 'fixed',
            top: '0',
            left: '0',
            height: '3px',
            width: '0',
            backgroundColor: '#3b82f6',
            zIndex: '9999',
            transition: 'width 0.3s ease, opacity 0.3s ease',
            pointerEvents: 'none',
            opacity: '0'
        });
        document.body.appendChild(this.progressBar);

        document.addEventListener('click', (e) => this.handleLinkClick(e));

        if (this.options.prefetch) {
            document.addEventListener('mouseover', (e) => this.handleLinkHover(e));
        }

        if (this.options.viewportPrefetch) {
            this.initViewportPrefetch();
        }

        document.addEventListener('submit', (e) => this.handleFormSubmit(e));

        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.spa) {
                this.navigate(window.location.href, false);
            }
        });

        this.initializeComponents();
        document.addEventListener('click', (e) => this.handleOutsideClick(e));

        window.plugsSPAInitialized = true;
        console.log('Plugs SPA Bridge Optimized (v3.0).');
    }

    initViewportPrefetch() {
        this.prefetchObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const link = entry.target;
                    this.prefetch(link.href);
                    this.prefetchObserver.unobserve(link);
                }
            });
        }, { rootMargin: '50px' });

        this.observeLinks();
    }

    observeLinks(container = document) {
        if (!this.prefetchObserver) return;
        container.querySelectorAll('a[data-spa="true"]').forEach(link => {
            if (this.isInternalLink(link) && !this.cache.has(link.href)) {
                this.prefetchObserver.observe(link);
            }
        });
    }

    initializeComponents(container = document) {
        let components = Array.from(container.querySelectorAll('[data-plug-component]'));

        // If container is a component itself, it needs to be processed to bind listeners to its new children
        if (container !== document && container.hasAttribute && container.hasAttribute('data-plug-component')) {
            components.unshift(container);
            // Force re-initialization of the container's contents
            container._plugInitialized = false;
        }
        
        const processed = new Set();

        components.forEach(el => {
            if (el._plugInitialized || processed.has(el)) return;
            processed.add(el);

            // Life-cycle optimization: Batch attribute reads
            const dataset = el.dataset;
            const initAction = el.getAttribute('p-init');
            const pollInterval = parseInt(el.getAttribute('p-poll') || '0');

            if (initAction) this.callComponentAction(el, 'init', initAction);

            if (pollInterval > 0) {
                const pollAction = el.getAttribute('p-poll-action') || 'refresh';
                el._pollTimer = setInterval(async () => {
                    if (!document.body.contains(el)) {
                        clearInterval(el._pollTimer);
                        return;
                    }
                    if (el._isPolling) return;
                    el._isPolling = true;
                    try {
                        await this.callComponentAction(el, 'poll', pollAction);
                    } finally {
                        el._isPolling = false;
                    }
                }, pollInterval);
            }

            // Delegation-friendly listeners can be optimized, but here we keep them specific to the component for scoping
            el.querySelectorAll('[p-click]').forEach(actionEl => {
                actionEl.addEventListener('click', (e) => {
                    const confirmMsg = actionEl.getAttribute('p-confirm');
                    if (confirmMsg && !confirm(confirmMsg)) return;
                    e.preventDefault();
                    this.callComponentAction(el, 'click', actionEl.getAttribute('p-click'));
                });
            });

            // Re-use logic for change/blur
            ['change', 'blur'].forEach(evtType => {
                el.querySelectorAll(`[p-${evtType}]`).forEach(actionEl => {
                    actionEl.addEventListener(evtType, () => {
                        this.callComponentAction(el, evtType, actionEl.getAttribute(`p-${evtType}`), this.getInputValue(actionEl));
                    });
                });
            });

            el.querySelectorAll('form[p-submit]').forEach(formEl => {
                formEl.addEventListener('submit', (e) => {
                    const confirmMsg = formEl.getAttribute('p-confirm');
                    if (confirmMsg && !confirm(confirmMsg)) return;
                    e.preventDefault();
                    const formData = new FormData(formEl);
                    this.callComponentAction(el, 'submit', formEl.getAttribute('p-submit'), Object.fromEntries(formData.entries()));
                });
            });

            el.querySelectorAll('[p-keyup]').forEach(actionEl => {
                const actionRaw = actionEl.getAttribute('p-keyup');
                const isEnterOnly = actionRaw.endsWith('.enter');
                const action = isEnterOnly ? actionRaw.replace('.enter', '') : actionRaw;
                const debounceTime = parseInt(actionEl.getAttribute('p-debounce') || '0');

                let timer;
                actionEl.addEventListener('keyup', (e) => {
                    if (isEnterOnly && e.key !== 'Enter') return;
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        this.callComponentAction(el, 'keyup', action, this.getInputValue(actionEl));
                    }, debounceTime);
                });
            });

            el.querySelectorAll('[p-intersect]').forEach(actionEl => {
                const action = actionEl.getAttribute('p-intersect');
                const observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting) {
                        this.callComponentAction(el, 'intersect', action);
                        observer.disconnect();
                    }
                });
                observer.observe(actionEl);
            });

            el._plugInitialized = true;
        });

        // After components are initialized, observe links for prefetching
        this.observeLinks(container);
    }

    handleOutsideClick(e) {
        document.querySelectorAll('[p-outside]').forEach(el => {
            if (!el.contains(e.target)) {
                this.callComponentAction(el, 'outside', el.getAttribute('p-outside'));
            }
        });
    }

    getInputValue(el) {
        if (el.type === 'checkbox') return el.checked;
        if (el.type === 'radio') return el.checked ? el.value : null;
        return el.value;
    }

    async callComponentAction(componentEl, eventType, action, payload = null) {
        const { plugComponent: name, plugState: state } = componentEl.dataset;
        const id = componentEl.id;

        if (!['intersect', 'keyup', 'poll'].includes(eventType)) {
            componentEl.style.opacity = '0.7';
        }

        const loadingSelector = componentEl.getAttribute('p-loading');
        let loadingEls = [];
        if (loadingSelector) {
            loadingEls = Array.from(componentEl.querySelectorAll(loadingSelector));
            loadingEls.forEach(el => el.style.display = '');
            componentEl.classList.add('p-is-loading');
        }

        try {
            const response = await fetch('/plugs/component/action', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ component: name, action, state, id, payload })
            });

            if (!response.ok) throw new Error('Action failed');

            this.handleFlashMessage(response);
            const result = await response.json();

            if (result.html) componentEl.innerHTML = result.html;
            if (result.state) componentEl.dataset.plugState = result.state;

            this.initializeComponents(componentEl);
        } catch (err) {
            console.error('Plugs Component Error:', err);
        } finally {
            componentEl.style.opacity = '1';
            if (loadingSelector) {
                loadingEls.forEach(el => el.style.display = 'none');
                componentEl.classList.remove('p-is-loading');
            }
        }
    }

    handleLinkClick(e) {
        const link = e.target.closest('a');
        if (!link || !this.isInternalLink(link) || link.dataset.spa !== 'true' || link.target === '_blank') return;

        const confirmMsg = link.getAttribute('p-confirm');
        if (confirmMsg && !confirm(confirmMsg)) {
            e.preventDefault();
            return;
        }

        e.preventDefault();
        const target = link.getAttribute('data-spa-target') || this.options.contentSelector;
        const method = (link.getAttribute('p-method') || 'GET').toUpperCase();

        this.navigate(link.href, true, target, method !== 'GET' ? { method } : {});
    }

    handleFormSubmit(e) {
        const form = e.target;
        if (form.dataset.spa !== 'true') return;

        const action = form.getAttribute('action') || window.location.href;
        const actionUrl = new URL(action, window.location.origin);
        if (actionUrl.host !== window.location.host) return;

        e.preventDefault();
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const target = form.getAttribute('data-spa-target') || this.options.contentSelector;

        this.navigate(action, true, target, {
            method,
            body: method === 'GET' ? null : new FormData(form)
        });
    }

    async load(url, targetSelector) {
        return this.navigate(url, false, targetSelector);
    }

    showProgress() {
        this.progressBar.style.opacity = '1';
        this.progressBar.style.width = '30%';
        this.progressTimer = setTimeout(() => {
            this.progressBar.style.width = '70%';
        }, 300);
    }

    hideProgress() {
        this.progressBar.style.opacity = '0';
        setTimeout(() => { this.progressBar.style.width = '0'; }, 300);
        clearTimeout(this.progressTimer);
    }

    handleLinkHover(e) {
        const link = e.target.closest('a');
        if (!link || !this.isInternalLink(link) || link.dataset.spa !== 'true' || this.cache.has(link.href)) return;

        clearTimeout(link._prefetchTimer);
        link._prefetchTimer = setTimeout(() => this.prefetch(link.href), 150);
    }

    async prefetch(url) {
        if (this.cache.has(url)) {
            const cachedAt = this.cacheTimestamps.get(url);
            if (Date.now() - cachedAt < this.options.cacheTTL) return;
        }

        try {
            const response = await fetch(url, {
                headers: { 'X-Plugs-SPA': 'true', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (response.ok) {
                const html = await response.text();
                this.cache.set(url, html);
                this.cacheTimestamps.set(url, Date.now());
                this.cleanCache();
            }
        } catch (e) { }
    }

    isInternalLink(link) {
        return link.host === window.location.host;
    }

    handleFlashMessage(response) {
        const flash = response.headers.get('X-Plugs-Flash');
        if (flash) {
            try {
                const message = JSON.parse(flash);
                window.dispatchEvent(new CustomEvent('plugs:flash', { detail: message }));
            } catch (e) { }
        }
    }

    async navigate(url, pushState = true, targetSelector = null, fetchOptions = {}) {
        targetSelector = targetSelector || this.options.contentSelector;
        const contentArea = document.querySelector(targetSelector);

        if (!contentArea) {
            if (pushState && targetSelector === this.options.contentSelector) window.location.href = url;
            return false;
        }

        this.options.onNavigate(url);
        document.body.classList.add(this.options.loaderClass);
        this.showProgress();

        const useViewTransition = document.startViewTransition && targetSelector === this.options.contentSelector;
        const skeletonType = contentArea.getAttribute('data-spa-skeleton');
        if (skeletonType && !useViewTransition) contentArea.innerHTML = this.getSkeletonPlaceholder(skeletonType);

        try {
            const headers = {
                'X-Plugs-SPA': 'true',
                'X-Requested-With': 'XMLHttpRequest',
                ...(fetchOptions.headers || {})
            };

            if (targetSelector !== this.options.contentSelector) {
                headers['X-Plugs-Section'] = targetSelector.replace(/^[#.]/, '');
            }

            const isMainContent = targetSelector === this.options.contentSelector;
            let html;

            if (isMainContent && (fetchOptions.method || 'GET').toUpperCase() === 'GET' && this.cache.has(url)) {
                const cachedAt = this.cacheTimestamps.get(url);
                if (Date.now() - cachedAt < this.options.cacheTTL) html = this.cache.get(url);
            }

            if (!html) {
                const response = await fetch(url, { ...fetchOptions, headers });
                if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

                this.handleFlashMessage(response);

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const json = await response.json();
                    if (json.redirect) return this.navigate(json.redirect, true);
                    return true;
                }

                html = await response.text();
                if (isMainContent && (fetchOptions.method || 'GET').toUpperCase() === 'GET') {
                    this.cache.set(url, html);
                    this.cacheTimestamps.set(url, Date.now());
                    this.cleanCache();
                }
            }

            const titleMatch = html.match(/<title>(.*?)<\/title>/i);
            if (titleMatch && titleMatch[1] && isMainContent) document.title = titleMatch[1];

            const layoutMatch = html.match(/<meta name="plugs-layout" content="(.*?)">/i);
            const currentLayoutMeta = document.querySelector('meta[name="plugs-layout"]');
            const currentLayout = currentLayoutMeta ? currentLayoutMeta.content : null;

            if (layoutMatch && layoutMatch[1] && currentLayout && layoutMatch[1] !== currentLayout) {
                window.location.href = url;
                return true;
            }

            const performUpdate = () => {
                if (this.currentView?.unmount) {
                    try { this.currentView.unmount(); } catch (e) { }
                }
                this.currentView = null;

                // Content update via requestAnimationFrame for perceived smoothness
                requestAnimationFrame(() => {
                    contentArea.innerHTML = html;
                    contentArea.querySelectorAll('script').forEach(oldScript => {
                        const newScript = document.createElement('script');
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                    this.initializeComponents(contentArea);
                    if (pushState && isMainContent) {
                        window.history.pushState({ spa: true }, '', url);
                        window.scrollTo(0, 0);
                    }
                    this.options.onComplete(url);
                    this.hideProgress();
                    document.body.classList.remove(this.options.loaderClass);
                });
            };

            if (useViewTransition) {
                await document.startViewTransition(performUpdate).finished;
            } else {
                performUpdate();
            }

            return true;
        } catch (err) {
            this.options.onError(err);
            this.hideProgress();
            document.body.classList.remove(this.options.loaderClass);
            return false;
        }
    }

    getSkeletonPlaceholder(type) {
        const shimmer = '<div class="plugs-skeleton" style="width: 100%; height: 20px; border-radius: 4px; margin-bottom: 10px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite;"></div>';
        
        // Add shimmer animation style if not exists
        if (!document.getElementById('plugs-skeleton-style')) {
            const style = document.createElement('style');
            style.id = 'plugs-skeleton-style';
            style.innerHTML = `@keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }`;
            document.head.appendChild(style);
        }

        switch (type) {
            case 'card':
                return `<div class="p-4 border rounded-lg">${shimmer.repeat(4)}</div>`;
            case 'list':
                return `<div class="space-y-4">${shimmer.repeat(5)}</div>`;
            default:
                return shimmer.repeat(3);
        }
    }
}

if (!window.plugsSPA) {
    window.plugsSPA = new PlugsSPA();
    window.Plugs = window.plugsSPA;
}
JS;
    }
}
