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
        console.log('Plugs SPA Bridge (v3.2).');
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

        if (container !== document && container.hasAttribute && container.hasAttribute('data-plug-component')) {
            components.unshift(container);
            container._plugInitialized = false;
        }

        const processed = new Set();

        components.forEach(el => {
            if (el._plugInitialized || processed.has(el)) return;
            processed.add(el);

            const initAction = el.getAttribute('p-init') || el.querySelector('[p-init]')?.getAttribute('p-init');
            const pollEl = el.hasAttribute('p-poll') ? el : el.querySelector('[p-poll]');
            const pollInterval = parseInt(pollEl?.getAttribute('p-poll') || '0');

            if (initAction && !el._initCalled) {
                el._initCalled = true;
                this.callComponentAction(el, 'init', initAction);
            }

            if (pollInterval > 0 && pollEl && !el._pollTimer) {
                const pollAction = pollEl.getAttribute('p-poll-action') || 'refresh';
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

            const filterToBound = (selector) => {
                return Array.from(el.querySelectorAll(selector)).filter(child => child.closest('[data-plug-component]') === el);
            };

            // p-click
            filterToBound('[p-click]').forEach(actionEl => {
                if (actionEl._plugBoundClick) return;
                actionEl._plugBoundClick = true;
                actionEl.addEventListener('click', (e) => {
                    const confirmMsg = actionEl.getAttribute('p-confirm');
                    if (confirmMsg && !confirm(confirmMsg)) return;
                    e.preventDefault();
                    this.callComponentAction(el, 'click', actionEl.getAttribute('p-click'));
                });
            });

            // p-change, p-blur
            ['change', 'blur'].forEach(evtType => {
                filterToBound(`[p-${evtType}]`).forEach(actionEl => {
                    if (actionEl[`_plugBound${evtType}`]) return;
                    actionEl[`_plugBound${evtType}`] = true;
                    actionEl.addEventListener(evtType, () => {
                        this.callComponentAction(el, evtType, actionEl.getAttribute(`p-${evtType}`), this.getInputValue(actionEl));
                    });
                });
            });

            // p-submit
            filterToBound('form[p-submit]').forEach(formEl => {
                if (formEl._plugBoundSubmit) return;
                formEl._plugBoundSubmit = true;
                formEl.addEventListener('submit', (e) => {
                    const confirmMsg = formEl.getAttribute('p-confirm');
                    if (confirmMsg && !confirm(confirmMsg)) return;
                    e.preventDefault();
                    const formData = new FormData(formEl);
                    this.callComponentAction(el, 'submit', formEl.getAttribute('p-submit'), Object.fromEntries(formData.entries()));
                });
            });

            // p-keyup
            filterToBound('[p-keyup]').forEach(actionEl => {
                if (actionEl._plugBoundKeyup) return;
                actionEl._plugBoundKeyup = true;
                const actionRaw = actionEl.getAttribute('p-keyup');
                const isEnterOnly = actionRaw.endsWith('.enter');
                const action = isEnterOnly ? actionRaw.replace('.enter', '') : actionRaw;
                const debounceTime = parseInt(actionEl.getAttribute('p-debounce') || '300');

                let timer;
                const handler = (e) => {
                    if (isEnterOnly && e.key !== 'Enter') return;
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        this.callComponentAction(el, 'keyup', action, this.getInputValue(actionEl));
                    }, debounceTime);
                };

                actionEl.addEventListener('keyup', handler);
                actionEl.addEventListener('input', (e) => {
                    if (!isEnterOnly) handler(e);
                });
            });

            // p-keydown
            filterToBound('[p-keydown]').forEach(actionEl => {
                if (actionEl._plugBoundKeydown) return;
                actionEl._plugBoundKeydown = true;
                const actionRaw = actionEl.getAttribute('p-keydown');
                const isEnterOnly = actionRaw.endsWith('.enter');
                const action = isEnterOnly ? actionRaw.replace('.enter', '') : actionRaw;
                const debounceTime = parseInt(actionEl.getAttribute('p-debounce') || '0');

                let timer;
                actionEl.addEventListener('keydown', (e) => {
                    if (isEnterOnly && e.key !== 'Enter') return;
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        this.callComponentAction(el, 'keydown', action, this.getInputValue(actionEl));
                    }, debounceTime);
                });
            });

            // p-input (real-time input tracking)
            filterToBound('[p-input]').forEach(actionEl => {
                if (actionEl._plugBoundInput) return;
                actionEl._plugBoundInput = true;
                const action = actionEl.getAttribute('p-input');
                const debounceTime = parseInt(actionEl.getAttribute('p-debounce') || '300');

                let timer;
                actionEl.addEventListener('input', () => {
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        this.callComponentAction(el, 'input', action, this.getInputValue(actionEl));
                    }, debounceTime);
                });
            });

            // p-intersect
            filterToBound('[p-intersect]').forEach(actionEl => {
                if (actionEl._plugBoundIntersect) return;
                actionEl._plugBoundIntersect = true;
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

        this.observeLinks(container);
    }

    handleOutsideClick(e) {
        if (e.defaultPrevented) return;
        document.querySelectorAll('[p-outside]').forEach(actionEl => {
            if (!actionEl.contains(e.target)) {
                const componentEl = actionEl.closest('[data-plug-component]');
                if (!componentEl) return;
                if (componentEl._isProcessingQueue || componentEl._isPolling) return;
                this.callComponentAction(componentEl, 'outside', actionEl.getAttribute('p-outside'));
            }
        });
    }

    // ─── DOM Morphing Engine ────────────────────────────────────

  morphInner(el, newHTML) {
    const temp = document.createElement("div");
    temp.innerHTML = newHTML.trim();
    const newEl = temp.firstElementChild;

    // If it's a single-root update matching the existing element (ideal for components), morph the element itself.
    // This ensures root attributes like data-plug-state are correctly synced.
    if (newEl && temp.childElementCount === 1 && (newEl.tagName === el.tagName || el.hasAttribute('data-plug-component'))) {
      this._morphElement(el, newEl);
    } else {
      // Fallback for multi-root fragments or non-matching tags: morph the children.
      this._morphChildNodes(el, temp);
    }
  }

  _morphChildNodes(target, source) {
    const oldNodes = Array.from(target.childNodes);
    const newNodes = Array.from(source.childNodes);
    const oldById = new Map();

    oldNodes.forEach((node) => {
      if (node.nodeType === 1 && node.id) oldById.set(node.id, node);
    });

    let oldIdx = 0;
    const processedOldNodes = new Set();

    for (let newIdx = 0; newIdx < newNodes.length; newIdx++) {
      const newChild = newNodes[newIdx];
      let oldChild = oldNodes[oldIdx];

      while (oldChild && processedOldNodes.has(oldChild)) {
        oldIdx++;
        oldChild = oldNodes[oldIdx];
      }

      if (newChild.nodeType === 1 && newChild.id && oldById.has(newChild.id)) {
        const matched = oldById.get(newChild.id);
        processedOldNodes.add(matched);
        if (matched !== oldChild) {
          target.insertBefore(matched, oldChild || null);
        } else {
          oldIdx++;
        }
        this._morphElement(matched, newChild);
        continue;
      }

      if (oldChild) {
        if (
          oldChild.nodeType === newChild.nodeType &&
          (oldChild.nodeType !== 1 || oldChild.tagName === newChild.tagName)
        ) {
          processedOldNodes.add(oldChild);
          if (oldChild.nodeType === 1) {
            this._morphElement(oldChild, newChild);
          } else if (oldChild.nodeValue !== newChild.nodeValue) {
            oldChild.nodeValue = newChild.nodeValue;
          }
          oldIdx++;
        } else {
          target.insertBefore(newChild.cloneNode(true), oldChild);
        }
      } else {
        target.appendChild(newChild.cloneNode(true));
      }
    }

    oldNodes.forEach((node) => {
      if (!processedOldNodes.has(node)) {
        target.removeChild(node);
      }
    });
  }

  _morphElement(oldEl, newEl) {
    const oldAttrs = oldEl.attributes;
    const newAttrs = newEl.attributes;

    for (let i = 0; i < newAttrs.length; i++) {
      const attr = newAttrs[i];
      if (oldEl.getAttribute(attr.name) !== attr.value) {
        oldEl.setAttribute(attr.name, attr.value);
      }
    }

    for (let i = oldAttrs.length - 1; i >= 0; i--) {
      const name = oldAttrs[i].name;
      if (!newEl.hasAttribute(name)) {
        oldEl.removeAttribute(name);
      }
    }

    const isFocused = document.activeElement === oldEl;

    if (oldEl.tagName === "INPUT" || oldEl.tagName === "TEXTAREA") {
      if (!isFocused || oldEl.type === "checkbox" || oldEl.type === "radio") {
        if (oldEl.value !== newEl.value) oldEl.value = newEl.value;
      }
      if (oldEl.checked !== newEl.checked) oldEl.checked = newEl.checked;
    } else if (oldEl.tagName === "SELECT") {
      if (oldEl.value !== newEl.value) oldEl.value = newEl.value;
    } else if (oldEl.tagName === "OPTION") {
      if (oldEl.selected !== newEl.selected) oldEl.selected = newEl.selected;
    }

    if (!this._isVoidElement(oldEl)) {
      this._morphChildNodes(oldEl, newEl);
    }
  }

    _isVoidElement(el) {
        return ['AREA','BASE','BR','COL','EMBED','HR','IMG','INPUT','LINK','META','SOURCE','TRACK','WBR'].includes(el.tagName);
    }

    // ─── End DOM Morphing Engine ────────────────────────────────

    normalizeHTML(html) {
        const div = document.createElement('div');
        div.innerHTML = html.trim();
        return div.innerHTML.replace(/\s+/g, ' ');
    }

    getInputValue(el) {
        if (el.type === 'checkbox') return el.checked;
        if (el.type === 'radio') return el.checked ? el.value : null;
        if (el.tagName === 'SELECT' && el.multiple) {
            return Array.from(el.selectedOptions).map(opt => opt.value);
        }
        return el.value;
    }

    async callComponentAction(element, eventType, action, payload = null) {
        const componentEl = element.closest('[data-plug-component]');
        if (!componentEl) return;

        if (!componentEl._requestQueue) componentEl._requestQueue = [];

        if (eventType === 'poll') {
            if (componentEl._requestQueue.some(r => r.eventType === 'poll')) return;
        } else if (eventType === 'keyup' || eventType === 'keydown' || eventType === 'input') {
            const existing = componentEl._requestQueue.find(r => r.eventType === 'keyup' || r.eventType === 'keydown' || r.eventType === 'input');
            if (existing) {
                existing.payload = payload;
                return;
            }
        }

        componentEl._requestQueue.push({ eventType, action, payload });

        if (componentEl._isProcessingQueue) return;
        this.processRequestQueue(componentEl);
    }

    async processRequestQueue(componentEl) {
        if (!componentEl._requestQueue?.length) {
            componentEl._isProcessingQueue = false;
            return;
        }

        componentEl._isProcessingQueue = true;
        const { eventType, action, payload } = componentEl._requestQueue.shift();

        const { plugComponent: name, plugState: state } = componentEl.dataset;
        const id = componentEl.id;

        const loadingSelector = componentEl.getAttribute('p-loading');
        let loadingEls = [];
        const showLoading = ['click', 'submit'].includes(eventType);
        if (loadingSelector && showLoading) {
            const filterToBound = (selector) => {
                return Array.from(componentEl.querySelectorAll(selector)).filter(child => child.closest('[data-plug-component]') === componentEl);
            };
            loadingEls = filterToBound(loadingSelector);
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

            if (result.html !== undefined) {
                const normalizedNew = this.normalizeHTML(result.html);
                const normalizedOld = this.normalizeHTML(componentEl.innerHTML);

                if (normalizedNew !== normalizedOld) {
                    this.morphInner(componentEl, result.html);
                }
            }
            if (result.state) componentEl.dataset.plugState = result.state;

            this.initializeComponents(componentEl);
        } catch (err) {
            console.error('Plugs Component Error:', err);
        } finally {
            if (showLoading && loadingSelector) {
                loadingEls.forEach(el => el.style.display = 'none');
                componentEl.classList.remove('p-is-loading');
            }
            this.processRequestQueue(componentEl);
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
        clearTimeout(this.progressTimer);
        this.progressBar.style.width = '100%';
        setTimeout(() => {
            this.progressBar.style.opacity = '0';
            setTimeout(() => { this.progressBar.style.width = '0'; }, 300);
        }, 200);
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

    async navigate(
        url,
        pushState = true,
        targetSelector = null,
        fetchOptions = {},
    ) {
        targetSelector = targetSelector || this.options.contentSelector;
        const contentArea = document.querySelector(targetSelector);

        if (!contentArea) {
            if (pushState && targetSelector === this.options.contentSelector)
                window.location.href = url;
            return false;
        }

        this.options.onNavigate(url);
        document.body.classList.add(this.options.loaderClass);
        this.showProgress();

        const useViewTransition =
            document.startViewTransition &&
            targetSelector === this.options.contentSelector;
        const skeletonType = contentArea.getAttribute("data-spa-skeleton");
        if (skeletonType && !useViewTransition)
            contentArea.innerHTML = this.getSkeletonPlaceholder(skeletonType);

        try {
            const headers = {
                "X-Plugs-SPA": "true",
                "X-Requested-With": "XMLHttpRequest",
                ...(fetchOptions.headers || {}),
            };

            if (targetSelector !== this.options.contentSelector) {
                headers["X-Plugs-Section"] = targetSelector.replace(/^[#.]/, "");
            }

            const isMainContent = targetSelector === this.options.contentSelector;
            let html;

            if (
                isMainContent &&
                (fetchOptions.method || "GET").toUpperCase() === "GET" &&
                this.cache.has(url)
            ) {
                const cachedAt = this.cacheTimestamps.get(url);
                if (Date.now() - cachedAt < this.options.cacheTTL)
                    html = this.cache.get(url);
            }

            if (!html) {
                const response = await fetch(url, { ...fetchOptions, headers });
                if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

                this.handleFlashMessage(response);

                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    const json = await response.json();
                    if (json.redirect) return this.navigate(json.redirect, true);
                    return true;
                }
                html = await response.text();
                if (
                    isMainContent &&
                    (fetchOptions.method || "GET").toUpperCase() === "GET"
                ) {
                    this.cache.set(url, html);
                    this.cacheTimestamps.set(url, Date.now());
                    this.cleanCache();
                }
            }

            // ─── Parse SPA response using DOMParser ───
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, "text/html");

            if (isMainContent) {
                const newTitle = doc.querySelector("title");
                if (newTitle) document.title = newTitle.innerText;
            }

            const layoutMatch = doc.querySelector('meta[name="plugs-layout"]');
            const currentLayoutMeta = document.querySelector(
                'meta[name="plugs-layout"]',
            );
            const currentLayout = currentLayoutMeta
                ? currentLayoutMeta.content
                : null;

            if (
                layoutMatch &&
                layoutMatch.content &&
                currentLayout &&
                layoutMatch.content !== currentLayout
            ) {
                window.location.href = url;
                return true;
            }

            // Extract styles and scripts before they move to #app-content
            const styleFragments = Array.from(
                doc.querySelectorAll('style, link[rel="stylesheet"]'),
            );
            const scriptFragments = Array.from(doc.querySelectorAll("script"));

            // Remove infrastructure from the parsed document so only content remains
            doc
                .querySelectorAll('title, meta[name="plugs-layout"]')
                .forEach((el) => el.remove());
            styleFragments.forEach((el) => el.remove());
            scriptFragments.forEach((el) => el.remove());

            const cleanHTML = doc.body.innerHTML;

            const performUpdate = () => {
                if (this.currentView?.unmount) {
                    try {
                        this.currentView.unmount();
                    } catch (e) { }
                }
                this.currentView = null;

                // Clean up previously injected SPA styles/scripts
                document
                    .querySelectorAll("[data-spa-injected]")
                    .forEach((el) => el.remove());

                // Set only the actual page content
                contentArea.innerHTML = cleanHTML;

                // Inject extracted styles into <head>
                styleFragments.forEach((el) => {
                    el.setAttribute("data-spa-injected", "true");
                    document.head.appendChild(el);
                });

                // Inject extracted scripts into <body>
                scriptFragments.forEach((oldScript) => {
                    const newScript = document.createElement("script");
                    Array.from(oldScript.attributes).forEach((attr) =>
                        newScript.setAttribute(attr.name, attr.value),
                    );
                    newScript.setAttribute("data-spa-injected", "true");
                    if (oldScript.innerHTML) {
                        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    }
                    document.body.appendChild(newScript);
                });

                // Re-initialize components on the new content
                this.initializeComponents(contentArea);

                if (pushState && isMainContent) {
                    window.history.pushState({ spa: true }, "", url);
                    window.scrollTo(0, 0);
                }

                this.options.onComplete(url);

                if (window.AOS) {
                    window.AOS.refresh();
                }

                this.hideProgress();
                document.body.classList.remove(this.options.loaderClass);
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
