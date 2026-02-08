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
/**
 * Plugs Framework SPA Bridge
 * 
 * Lightweight SPA functionality for traditional PHP views.
 * Intercepts internal links and loads content via Fetch API.
 */
class PlugsSPA {
    constructor(options = {}) {
        this.options = {
            contentSelector: options.contentSelector || '#app-content',
            loaderClass: options.loaderClass || 'spa-loading',
            onNavigate: options.onNavigate || (() => { }),
            onComplete: options.onComplete || (() => { }),
            onError: options.onError || ((err) => console.error('SPA Navigation Error:', err)),
            prefetch: options.prefetch !== false, // Default to true
            cacheMaxSize: options.cacheMaxSize || 50, // Maximum cache entries
            cacheTTL: options.cacheTTL || 300000, // 5 minutes in milliseconds
        };

        this.cache = new Map();
        this.cacheTimestamps = new Map(); // Track when entries were cached
        this.currentView = null; // Track active view controller

        this.init();
    }

    /**
     * Clean expired cache entries and enforce size limits
     */
    cleanCache() {
        // Enforce size limit (remove oldest entries first)
        if (this.cache.size <= this.options.cacheMaxSize) return;

        while (this.cache.size > this.options.cacheMaxSize) {
            const oldestUrl = this.cache.keys().next().value;
            this.cache.delete(oldestUrl);
            this.cacheTimestamps.delete(oldestUrl);
        }
    }

    /**
     * Define the view controller for the current page.
     * Call this in an inline script within your view.
     * 
     * Pattern 1 (Object): Plugs.view({ mount() { ... }, unmount() { ... } })
     * Pattern 2 (Function): Plugs.view(() => { ...; return () => { cleanup... } })
     * 
     * @param {object|function} controller 
     */
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

        // Create Progress Bar
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

        // Intercept link clicks
        document.addEventListener('click', (e) => this.handleLinkClick(e));

        // Prefetch on hover
        if (this.options.prefetch) {
            document.addEventListener('mouseover', (e) => this.handleLinkHover(e));
        }

        // Intercept form submissions
        document.addEventListener('submit', (e) => this.handleFormSubmit(e));

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.spa) {
                this.navigate(window.location.href, false);
            }
        });

        // Initialize Reactive Components
        this.initializeComponents();

        // Handle clicks outside for p-outside
        document.addEventListener('click', (e) => this.handleOutsideClick(e));

        window.plugsSPAInitialized = true;
        console.log('Plugs SPA Bridge initialized (v2.3).');
    }

    initializeComponents(container = document) {
        let components = Array.from(container.querySelectorAll('[data-plug-component]'));

        // If container is a component itself, add it to the list
        if (container.hasAttribute && container.hasAttribute('data-plug-component')) {
            components.unshift(container);
            // Force re-initialization for updated components
            container._plugInitialized = false;
        }

        components.forEach(el => {
            if (el._plugInitialized) return;

            // p-init
            const initAction = el.getAttribute('p-init');
            if (initAction) {
                this.callComponentAction(el, 'init', initAction);
            }

            // p-poll
            const pollInterval = parseInt(el.getAttribute('p-poll') || '0');
            if (pollInterval > 0) {
                const pollAction = el.getAttribute('p-poll-action') || 'refresh';
                el._pollTimer = setInterval(async () => {
                    if (!document.body.contains(el)) {
                        clearInterval(el._pollTimer);
                        return;
                    }
                    if (el._isPolling) return; // Prevent stacking
                    el._isPolling = true;
                    try {
                        await this.callComponentAction(el, 'poll', pollAction);
                    } finally {
                        el._isPolling = false;
                    }
                }, pollInterval);
            }

            // p-click
            el.querySelectorAll('[p-click]').forEach(actionEl => {
                actionEl.addEventListener('click', (e) => {
                    const confirmMsg = actionEl.getAttribute('p-confirm');
                    if (confirmMsg && !confirm(confirmMsg)) return;

                    e.preventDefault();
                    this.callComponentAction(el, 'click', actionEl.getAttribute('p-click'));
                });
            });

            // p-change (inputs, selects)
            el.querySelectorAll('[p-change]').forEach(actionEl => {
                actionEl.addEventListener('change', (e) => {
                    this.callComponentAction(el, 'change', actionEl.getAttribute('p-change'), this.getInputValue(actionEl));
                });
            });

            // p-submit (forms)
            el.querySelectorAll('form[p-submit]').forEach(formEl => {
                formEl.addEventListener('submit', (e) => {
                    const confirmMsg = formEl.getAttribute('p-confirm');
                    if (confirmMsg && !confirm(confirmMsg)) return;

                    e.preventDefault();
                    const formData = new FormData(formEl);
                    const data = Object.fromEntries(formData.entries());
                    this.callComponentAction(el, 'submit', formEl.getAttribute('p-submit'), data);
                });
            });

            // p-blur
            el.querySelectorAll('[p-blur]').forEach(actionEl => {
                actionEl.addEventListener('blur', (e) => {
                    this.callComponentAction(el, 'blur', actionEl.getAttribute('p-blur'), this.getInputValue(actionEl));
                });
            });

            // p-keyup (with p-debounce and .enter support)
            el.querySelectorAll('[p-keyup]').forEach(actionEl => {
                const actionRaw = actionEl.getAttribute('p-keyup');
                const isEnterOnly = actionRaw.endsWith('.enter');
                const action = isEnterOnly ? actionRaw.replace('.enter', '') : actionRaw;
                const debounceTime = parseInt(actionEl.getAttribute('p-debounce') || '0');

                let timer;
                actionEl.addEventListener('keyup', (e) => {
                    if (isEnterOnly && e.key !== 'Enter') return;

                    if (timer) clearTimeout(timer);
                    timer = setTimeout(() => {
                        this.callComponentAction(el, 'keyup', action, this.getInputValue(actionEl));
                    }, debounceTime);
                });
            });

            // p-intersect
            el.querySelectorAll('[p-intersect]').forEach(actionEl => {
                const action = actionEl.getAttribute('p-intersect');
                const observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting) {
                        this.callComponentAction(el, 'intersect', action);
                        observer.disconnect(); // Typically intersect is a one-time trigger like "visible"
                    }
                });
                observer.observe(actionEl);
            });

            el._plugInitialized = true;
        });
    }

    handleOutsideClick(e) {
        document.querySelectorAll('[p-outside]').forEach(el => {
            if (!el.contains(e.target)) {
                const action = el.getAttribute('p-outside');
                this.callComponentAction(el, 'outside', action);
            }
        });
    }

    getInputValue(el) {
        if (el.type === 'checkbox') return el.checked;
        if (el.type === 'radio') return el.checked ? el.value : null;
        return el.value;
    }

    async callComponentAction(componentEl, eventType, action, payload = null) {
        const name = componentEl.dataset.plugComponent;
        const state = componentEl.dataset.plugState;
        const id = componentEl.id;

        // Visual feedback (unless specifically disabled or it's a background event like intersect/keyup)
        if (eventType !== 'intersect' && eventType !== 'keyup' && eventType !== 'poll') {
            componentEl.style.opacity = '0.7';
        }

        // Loading Indicator Support
        const loadingSelector = componentEl.getAttribute('p-loading');
        let loadingEls = [];
        if (loadingSelector) {
            loadingEls = Array.from(componentEl.querySelectorAll(loadingSelector));
            loadingEls.forEach(el => el.style.display = ''); // Assuming they are hidden by default or we just show them
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
                body: JSON.stringify({
                    component: name,
                    action: action,
                    state: state,
                    id: id,
                    payload: payload
                })
            });

            if (!response.ok) throw new Error('Action failed');

            this.handleFlashMessage(response);

            const result = await response.json();

            // Update HTML
            if (result.html) {
                componentEl.innerHTML = result.html;
            }

            if (result.state) {
                componentEl.dataset.plugState = result.state;
            }

            // Re-initialize children if they are components
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

        if (!link || !this.isInternalLink(link)) {
            return;
        }

        // Only intercept if data-spa="true" is set
        if (link.dataset.spa !== 'true') {
            return;
        }

        // p-confirm support
        const confirmMsg = link.getAttribute('p-confirm');
        if (confirmMsg && !confirm(confirmMsg)) {
            e.preventDefault();
            return;
        }

        // Skip if link has target="_blank" (as a safety measure)
        if (link.target === '_blank') {
            return;
        }

        e.preventDefault();
        const url = link.href;
        const target = link.getAttribute('data-spa-target') || this.options.contentSelector;
        const method = (link.getAttribute('p-method') || 'GET').toUpperCase();

        if (method !== 'GET') {
            this.navigate(url, true, target, { method });
        } else {
            this.navigate(url, true, target);
        }
    }

    handleFormSubmit(e) {
        const form = e.target;
        // Only intercept if data-spa="true" is set
        if (form.dataset.spa !== 'true') return;

        // Ensure form action is internal
        const action = form.getAttribute('action') || window.location.href;
        const actionUrl = new URL(action, window.location.origin);
        if (actionUrl.host !== window.location.host) return;

        e.preventDefault();

        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const formData = new FormData(form);
        const target = form.getAttribute('data-spa-target') || this.options.contentSelector;

        this.navigate(action, true, target, {
            method,
            body: method === 'GET' ? null : formData
        });
    }

    /**
     * Manual load method to pull content into a specific target
     * @param {string} url 
     * @param {string} targetSelector 
     */
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
        this.progressBar.style.width = '100%';
        setTimeout(() => {
            this.progressBar.style.opacity = '0';
            setTimeout(() => {
                this.progressBar.style.width = '0';
            }, 300);
        }, 200);
        clearTimeout(this.progressTimer);
    }

    handleLinkHover(e) {
        const link = e.target.closest('a');
        if (!link || !this.isInternalLink(link) || link.dataset.spa !== 'true') return;

        const url = link.href;
        if (this.cache.has(url)) return;

        // Debounce prefetch
        clearTimeout(link._prefetchTimer);
        link._prefetchTimer = setTimeout(() => {
            if (this.cache.has(url)) return;
            this.prefetch(url);
        }, 150);
    }

    async prefetch(url) {
        // Check if already in cache and not expired
        if (this.cache.has(url)) {
            const cachedAt = this.cacheTimestamps.get(url);
            if (Date.now() - cachedAt < this.options.cacheTTL) {
                return;
            }
        }

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Plugs-SPA': 'true',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (response.ok) {
                const html = await response.text();
                this.cache.set(url, html);
                this.cacheTimestamps.set(url, Date.now());
                this.cleanCache();
            }
        } catch (e) {
            // Silently fail prefetch
        }
    }

    isInternalLink(link) {
        return link.host === window.location.host;
    }

    handleFlashMessage(response) {
        const flash = response.headers.get('X-Plugs-Flash');
        if (flash) {
            try {
                const message = JSON.parse(flash);
                // Dispatch event for other listeners
                window.dispatchEvent(new CustomEvent('plugs:flash', { detail: message }));

                // Default Toast (Simple alert if no UI library attached, can be overridden)
                console.log('Flash:', message);
            } catch (e) { }
        }
    }

    async navigate(url, pushState = true, targetSelector = null, fetchOptions = {}) {
        targetSelector = targetSelector || this.options.contentSelector;
        const contentArea = document.querySelector(targetSelector);

        if (!contentArea) {
            console.warn(`SPA: Target area "${targetSelector}" not found. Falling back to full reload.`);
            if (pushState && targetSelector === this.options.contentSelector) {
                window.location.href = url;
            }
            return false;
        }

        this.options.onNavigate(url);
        document.body.classList.add(this.options.loaderClass);
        this.showProgress();

        // Visual feedback (unless View Transitions active)
        const useViewTransition = document.startViewTransition && targetSelector === this.options.contentSelector;

        // Skeleton Support
        const skeletonType = contentArea.getAttribute('data-spa-skeleton');
        if (skeletonType && !useViewTransition) {
            contentArea.innerHTML = this.getSkeletonPlaceholder(skeletonType);
        }

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

            if (isMainContent && this.cache.has(url)) {
                // Check TTL even for manual navigation
                const cachedAt = this.cacheTimestamps.get(url);
                if (Date.now() - cachedAt < this.options.cacheTTL) {
                    html = this.cache.get(url);
                }
            }

            if (!html) {
                const response = await fetch(url, {
                    ...fetchOptions,
                    headers
                });

                if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

                this.handleFlashMessage(response);

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const json = await response.json();
                    if (json.redirect) {
                        return this.navigate(json.redirect, true);
                    }
                    return true;
                }

                html = await response.text();

                // Update cache
                if (isMainContent && (fetchOptions.method || 'GET').toUpperCase() === 'GET') {
                    this.cache.set(url, html);
                    this.cacheTimestamps.set(url, Date.now());
                    this.cleanCache();
                }
            }

            // Extract Title
            const titleMatch = html.match(/<title>(.*?)<\/title>/i);
            if (titleMatch && titleMatch[1] && isMainContent) {
                document.title = titleMatch[1];
            }

            // Layout Detection
            const layoutMatch = html.match(/<meta name="plugs-layout" content="(.*?)">/i);
            const currentLayoutMeta = document.querySelector('meta[name="plugs-layout"]');
            const currentLayout = currentLayoutMeta ? currentLayoutMeta.content : null;

            if (layoutMatch && layoutMatch[1] && currentLayout && layoutMatch[1] !== currentLayout) {
                console.log(`SPA: Layout mismatch detected (${currentLayout} -> ${layoutMatch[1]}). Performing full reload.`);
                window.location.href = url;
                return true;
            }

            const performUpdate = () => {
                // Unmount current view
                if (this.currentView && this.currentView.unmount) {
                    try { this.currentView.unmount(); } catch (e) { console.error('Unmount Error:', e); }
                }
                this.currentView = null;

                // Swap Content
                contentArea.innerHTML = html;

                // Execute Scripts
                contentArea.querySelectorAll('script').forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                // Initialize Reactivity
                this.initializeComponents(contentArea);

                // Update URL and Scroll
                if (pushState && isMainContent) {
                    window.history.pushState({ spa: true }, '', url);
                    window.scrollTo(0, 0);
                }
            };

            if (useViewTransition) {
                await document.startViewTransition(performUpdate).finished;
            } else {
                performUpdate();
            }

            this.options.onComplete(url);
            return true;
        } catch (err) {
            this.options.onError(err);
            return false;
        } finally {
            this.hideProgress();
            document.body.classList.remove(this.options.loaderClass);
        }
    }

    /**
     * Generate skeleton HTML for client-side placeholders
     * @param {string} type 
     */
    getSkeletonPlaceholder(type) {
        const shimmer = '<div class="plugs-skeleton" style="width: 100%; height: 20px; border-radius: 4px; margin-bottom: 10px;"></div>';

        switch (type) {
            case 'card':
                return `
                    <div class="plugs-skeleton-card" style="border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <div class="plugs-skeleton" style="width: 100%; height: 150px; border-radius: 4px; margin-bottom: 1rem;"></div>
                        <div class="plugs-skeleton" style="width: 60%; height: 16px; border-radius: 4px; margin-bottom: 0.5rem;"></div>
                        <div class="plugs-skeleton" style="width: 90%; height: 12px; border-radius: 4px; margin-bottom: 0.5rem;"></div>
                        <div class="plugs-skeleton" style="width: 40%; height: 12px; border-radius: 4px;"></div>
                    </div>
                `;
            case 'list':
                return `
                    <div class="plugs-skeleton-list">
                        ${[1, 2, 3].map(() => `
                            <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                                <div class="plugs-skeleton" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 1rem;"></div>
                                <div style="flex: 1;">
                                    <div class="plugs-skeleton" style="width: 50%; height: 16px; border-radius: 4px; margin-bottom: 0.5rem;"></div>
                                    <div class="plugs-skeleton" style="width: 80%; height: 12px; border-radius: 4px;"></div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            case 'table':
                return `
                    <div class="plugs-skeleton-table-wrapper" style="overflow-x: auto;">
                        <table class="table">
                            <thead><tr>${[1, 2, 3, 4].map(() => `<th><div class="plugs-skeleton" style="width: 80%; height: 20px; border-radius: 4px;"></div></th>`).join('')}</tr></thead>
                            <tbody>
                                ${[1, 2, 3, 4, 5].map(() => `
                                    <tr>${[1, 2, 3, 4].map(() => `<td><div class="plugs-skeleton" style="width: 100%; height: 15px; border-radius: 4px;"></div></td>`).join('')}</tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            default:
                return shimmer.repeat(3);
        }
    }
}

// Auto-initialize if script is loaded
if (!window.plugsSPA) {
    window.plugsSPA = new PlugsSPA();
    // Expose correctly
    window.Plugs = window.plugsSPA;
}
JS;
    }
}
