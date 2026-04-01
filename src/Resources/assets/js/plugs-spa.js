/**
 * Plugs SPA Bridge v5.0 — High-Performance Edition
 *
 * Single-file SPA engine with integrated lazy loading, DOM morphing,
 * reactive components, and viewport prefetching.
 *
 * @license MIT
 */
class PlugsSPA {
  constructor(options = {}) {
    this.options = {
      contentSelector: options.contentSelector || "#app-content",
      loaderClass: options.loaderClass || "spa-loading",
      onNavigate: options.onNavigate || (() => {}),
      onComplete: options.onComplete || (() => {}),
      onError:
        options.onError ||
        ((err) => console.error("SPA Navigation Error:", err)),
      prefetch: options.prefetch !== false,
      viewportPrefetch: options.viewportPrefetch !== false,
      cacheMaxSize: options.cacheMaxSize || 50,
      cacheTTL: options.cacheTTL || 300000,
      persistCache: options.persistCache !== false,
    };

    this.cache = new Map();
    this.cacheTimestamps = new Map();
    this.currentView = null;
    this.prefetchObserver = null;
    this.directives = new Map();
    this._baseUrl = null; // Cached base URL

    this.loadPersistentCache();
    this.registerBaseDirectives();
    this.init();
  }

  static directive(name, callback) {
    if (window.plugsSPA) {
      window.plugsSPA.directives.set(name, callback);
    } else {
      if (!window._plugsPendingDirectives) window._plugsPendingDirectives = [];
      window._plugsPendingDirectives.push({ name, callback });
    }
  }

  // ─── Cached Helpers ──────────────────────────────────────────

  /**
   * Get the application base URL (cached after first call).
   */
  getBaseUrl() {
    if (this._baseUrl !== null) return this._baseUrl;
    const meta = document.querySelector('meta[name="app-url"]');
    if (meta) {
      this._baseUrl = meta.content.endsWith("/")
        ? meta.content.slice(0, -1)
        : meta.content;
    } else {
      this._baseUrl = "";
    }
    return this._baseUrl;
  }

  /**
   * Filter elements that belong directly to a component (not nested).
   */
  filterToBound(el, selector) {
    return Array.from(el.querySelectorAll(selector)).filter(
      (child) => child.closest("[data-plug-component], [p-data]") === el,
    );
  }

  // ─── Persistent Cache ────────────────────────────────────────

  loadPersistentCache() {
    if (!this.options.persistCache) return;
    try {
      const stored = sessionStorage.getItem("plugs_spa_cache");
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
      // Silent fail — cache is optional
    }
  }

  savePersistentCache() {
    if (!this.options.persistCache) return;
    try {
      const data = {
        cache: Object.fromEntries(this.cache),
        timestamps: Object.fromEntries(this.cacheTimestamps),
      };
      sessionStorage.setItem("plugs_spa_cache", JSON.stringify(data));
    } catch (e) {
      if (e.name === "QuotaExceededError") {
        const entries = Array.from(this.cache.keys());
        for (let i = 0; i < entries.length / 2; i++) {
          this.cache.delete(entries[i]);
          this.cacheTimestamps.delete(entries[i]);
        }
      }
    }
  }

  cleanCache() {
    if (this.cache.size <= this.options.cacheMaxSize) return;

    const entries = Array.from(this.cacheTimestamps.entries()).sort(
      (a, b) => a[1] - b[1],
    );

    while (this.cache.size > this.options.cacheMaxSize) {
      const [oldestUrl] = entries.shift();
      this.cache.delete(oldestUrl);
      this.cacheTimestamps.delete(oldestUrl);
    }
    this.savePersistentCache();
  }

  view(controller) {
    if (typeof controller === "function") {
      const cleanup = controller();
      if (typeof cleanup === "function") {
        this.currentView = { unmount: cleanup };
      } else {
        this.currentView = null;
      }
    } else if (controller && typeof controller === "object") {
      this.currentView = controller;
      if (controller.mount) controller.mount();
    }
  }

  // ─── Initialization ──────────────────────────────────────────

  init() {
    if (window.plugsSPAInitialized) return;

    this.progressBar = document.createElement("div");
    this.progressBar.id = "spa-progress-bar";
    Object.assign(this.progressBar.style, {
      position: "fixed",
      top: "0",
      left: "0",
      height: "3px",
      width: "0",
      backgroundColor: "#3b82f6",
      zIndex: "9999",
      transition: "width 0.3s ease, opacity 0.3s ease",
      pointerEvents: "none",
      opacity: "0",
    });
    document.body.appendChild(this.progressBar);

    document.addEventListener("click", (e) => this.handleLinkClick(e));

    if (this.options.prefetch) {
      document.addEventListener("mouseover", (e) => this.handleLinkHover(e));
    }

    if (this.options.viewportPrefetch) {
      this.initViewportPrefetch();
    }

    document.addEventListener("submit", (e) => this.handleFormSubmit(e));

    window.addEventListener("popstate", (e) => {
      if (e.state && e.state.spa) {
        this.navigate(window.location.href, false);
      }
    });

    this.initializeComponents();
    document.addEventListener("click", (e) => this.handleOutsideClick(e));

    window.plugsSPAInitialized = true;

    // Flush pending directives
    if (window._plugsPendingDirectives) {
      window._plugsPendingDirectives.forEach(({ name, callback }) => {
        this.directives.set(name, callback);
      });
      delete window._plugsPendingDirectives;
    }
  }

  // ─── Base Directives ─────────────────────────────────────────

  registerBaseDirectives() {
    this.directive("text", (el, value) => {
      el.innerText = value;
    });

    this.directive("html", (el, value) => {
      el.innerHTML = value;
    });

    this.directive("show", (el, value) => {
      el.style.display = value ? "" : "none";
    });

    this.directive("if", (el, value) => {
      if (!value) {
        if (!el._pPlaceholder) {
          el._pPlaceholder = document.createComment("p-if");
          el.replaceWith(el._pPlaceholder);
        }
      } else {
        if (el._pPlaceholder) {
          el._pPlaceholder.replaceWith(el);
          delete el._pPlaceholder;
        }
      }
    });

    this.directive("bind", (el, value, arg) => {
      if (arg === "class") {
        if (typeof value === "object") {
          Object.entries(value).forEach(([cls, active]) => {
            el.classList.toggle(cls, !!active);
          });
        } else {
          el.className = value;
        }
      } else if (arg === "style") {
        if (typeof value === "object") {
          Object.assign(el.style, value);
        } else {
          el.style.cssText = value;
        }
      } else {
        if (value === false || value === null || value === undefined) {
          el.removeAttribute(arg);
        } else {
          el.setAttribute(arg, value === true ? arg : value);
        }
      }
    });

    this.directive("model", (el, value, arg, component) => {
      const isInput =
        el.tagName === "INPUT" ||
        el.tagName === "TEXTAREA" ||
        el.tagName === "SELECT";
      if (!isInput) return;

      if (el.type === "checkbox") {
        el.checked = !!value;
      } else if (el.type === "radio") {
        el.checked = el.value == value;
      } else {
        if (el.value !== value) el.value = value ?? "";
      }
    });
  }

  // ─── Transitions ─────────────────────────────────────────────

  async transition(el, stage) {
    const attr = el.getAttribute(`p-transition:${stage}`);
    if (!attr) return;

    const startAttr = el.getAttribute(`p-transition:${stage}-start`);
    const endAttr = el.getAttribute(`p-transition:${stage}-end`);

    const classes = attr.split(" ").filter(Boolean);
    const startClasses = (startAttr || "").split(" ").filter(Boolean);
    const endClasses = (endAttr || "").split(" ").filter(Boolean);

    el.classList.add(...classes);
    el.classList.add(...startClasses);

    await new Promise((r) => requestAnimationFrame(r));
    await new Promise((r) => requestAnimationFrame(r));

    el.classList.remove(...startClasses);
    el.classList.add(...endClasses);

    await new Promise((r) => {
      const handler = () => {
        el.removeEventListener("transitionend", handler);
        el.removeEventListener("animationend", handler);
        r();
      };
      el.addEventListener("transitionend", handler);
      el.addEventListener("animationend", handler);
      setTimeout(handler, 1000);
    });

    el.classList.remove(...classes);
    el.classList.remove(...endClasses);
  }

  directive(name, callback) {
    this.directives.set(name, callback);
  }

  // ─── Reactivity Core ─────────────────────────────────────────

  evaluate(expression, context = {}) {
    try {
      const keys = Object.keys(context);
      const values = Object.values(context);
      const fn = new Function(...keys, `return ${expression}`);
      return fn(...values);
    } catch (e) {
      return null;
    }
  }

  async runAction(expression, context = {}) {
    try {
      const keys = Object.keys(context);
      const values = Object.values(context);
      const fn = new Function(...keys, `${expression}`);
      return fn(...values);
    } catch (e) {
      // Silent fail
    }
  }

  createProxy(obj, onChange) {
    const self = this;
    const handler = {
      get(target, key) {
        const val = target[key];
        if (val !== null && typeof val === "object") {
          return self.createProxy(val, onChange);
        }
        return val;
      },
      set(target, key, value) {
        target[key] = value;
        onChange();
        return true;
      },
    };
    return new Proxy(obj, handler);
  }

  applyDirectives(container, state, componentEl) {
    const walker = document.createTreeWalker(
      container,
      NodeFilter.SHOW_ELEMENT,
      null,
      false,
    );

    let node = container;
    while (node) {
      if (node !== container && node.hasAttribute("p-data")) {
        node = walker.nextSibling() || walker.nextNode();
        continue;
      }

      const attrs = Array.from(node.attributes);
      attrs.forEach((attr) => {
        if (attr.name.startsWith("p-")) {
          const parts = attr.name.slice(2).split(":");
          const dirName = parts[0];
          const arg = parts[1];

          if (this.directives.has(dirName)) {
            const value = this.evaluate(attr.value, state);
            this.directives.get(dirName)(node, value, arg, componentEl);
          }
        }
      });

      node = walker.nextNode();
    }

    if (componentEl.getAttribute("p-updated")) {
      this.runAction(componentEl.getAttribute("p-updated"), {
        $el: componentEl,
        ...(componentEl._pState || {}),
      });
    }
  }

  refreshComponent(el) {
    if (!el._pState) return;
    this.applyDirectives(el, el._pState, el);
  }

  // ─── Viewport Prefetching ────────────────────────────────────

  initViewportPrefetch() {
    this.prefetchObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const link = entry.target;
            this.prefetch(link.href);
            this.prefetchObserver.unobserve(link);
          }
        });
      },
      { rootMargin: "50px" },
    );

    this.observeLinks();
  }

  observeLinks(container = document) {
    if (!this.prefetchObserver) return;
    container.querySelectorAll('a[data-spa="true"]').forEach((link) => {
      if (this.isInternalLink(link) && !this.cache.has(link.href)) {
        this.prefetchObserver.observe(link);
      }
    });
  }

  // ─── Component Initialization ────────────────────────────────

  initializeComponents(container = document) {
    this.initializeAsyncComponents(container);
    this.initializeFetchComponents(container);
    this.initializeLazyComponents(container);

    let components = Array.from(
      container.querySelectorAll("[data-plug-component], [p-data]"),
    );

    if (
      container !== document &&
      container.hasAttribute &&
      (container.hasAttribute("data-plug-component") || container.hasAttribute("p-data"))
    ) {
      components.unshift(container);
      container._plugInitialized = false;
    }

    const processed = new Set();

    components.forEach((el) => {
      if (el._plugInitialized || processed.has(el)) return;
      processed.add(el);

      // Handle p-data (Reactivity)
      if (el.hasAttribute("p-data")) {
        try {
          const rawData = this.evaluate(el.getAttribute("p-data") || "{}");
          el._pState = this.createProxy(rawData, () => this.refreshComponent(el));
          this.refreshComponent(el);
        } catch (e) {
          console.error("Plugs: p-data initialization failed", e);
        }
      }

      const initAction =
        el.getAttribute("p-init") ||
        el.querySelector("[p-init]")?.getAttribute("p-init");
      const pollEl = el.hasAttribute("p-poll")
        ? el
        : el.querySelector("[p-poll]");
      const pollInterval = parseInt(pollEl?.getAttribute("p-poll") || "0");

      if (initAction && !el._initCalled) {
        el._initCalled = true;
        this.callComponentAction(el, "init", initAction);
      }

      if (pollInterval > 0 && pollEl && !el._pollTimer) {
        const pollAction = pollEl.getAttribute("p-poll-action") || "refresh";
        el._pollTimer = setInterval(async () => {
          if (!document.body.contains(el)) {
            clearInterval(el._pollTimer);
            return;
          }
          if (el._isPolling) return;
          el._isPolling = true;
          try {
            await this.callComponentAction(el, "poll", pollAction);
          } finally {
            el._isPolling = false;
          }
        }, pollInterval);
      }

      // p-click
      this.filterToBound(el, "[p-click]").forEach((actionEl) => {
        if (actionEl._plugBoundClick) return;
        actionEl._plugBoundClick = true;
        actionEl.addEventListener("click", (e) => {
          const confirmMsg = actionEl.getAttribute("p-confirm");
          if (confirmMsg && !confirm(confirmMsg)) return;
          e.preventDefault();
          this.callComponentAction(
            el,
            "click",
            actionEl.getAttribute("p-click"),
          );
        });
      });

      // p-change, p-blur
      ["change", "blur"].forEach((evtType) => {
        this.filterToBound(el, `[p-${evtType}]`).forEach((actionEl) => {
          if (actionEl[`_plugBound${evtType}`]) return;
          actionEl[`_plugBound${evtType}`] = true;
          actionEl.addEventListener(evtType, () => {
            this.callComponentAction(
              el,
              evtType,
              actionEl.getAttribute(`p-${evtType}`),
              this.getInputValue(actionEl),
            );
          });
        });
      });

      // p-submit
      this.filterToBound(el, "form[p-submit]").forEach((formEl) => {
        if (formEl._plugBoundSubmit) return;
        formEl._plugBoundSubmit = true;
        formEl.addEventListener("submit", (e) => {
          const confirmMsg = formEl.getAttribute("p-confirm");
          if (confirmMsg && !confirm(confirmMsg)) return;
          e.preventDefault();
          const formData = new FormData(formEl);
          this.callComponentAction(
            el,
            "submit",
            formEl.getAttribute("p-submit"),
            Object.fromEntries(formData.entries()),
          );
        });
      });

      // p-keyup
      this.filterToBound(el, "[p-keyup]").forEach((actionEl) => {
        if (actionEl._plugBoundKeyup) return;
        actionEl._plugBoundKeyup = true;
        const actionRaw = actionEl.getAttribute("p-keyup");
        const isEnterOnly = actionRaw.endsWith(".enter");
        const action = isEnterOnly
          ? actionRaw.replace(".enter", "")
          : actionRaw;
        const debounceTime = parseInt(
          actionEl.getAttribute("p-debounce") || "300",
        );

        let timer;
        const handler = (e) => {
          if (isEnterOnly && e.key !== "Enter") return;
          clearTimeout(timer);
          timer = setTimeout(() => {
            this.callComponentAction(
              el,
              "keyup",
              action,
              this.getInputValue(actionEl),
            );
          }, debounceTime);
        };

        actionEl.addEventListener("keyup", handler);
        actionEl.addEventListener("input", (e) => {
          if (!isEnterOnly) handler(e);
        });
      });

      // p-keydown
      this.filterToBound(el, "[p-keydown]").forEach((actionEl) => {
        if (actionEl._plugBoundKeydown) return;
        actionEl._plugBoundKeydown = true;
        const actionRaw = actionEl.getAttribute("p-keydown");
        const isEnterOnly = actionRaw.endsWith(".enter");
        const action = isEnterOnly
          ? actionRaw.replace(".enter", "")
          : actionRaw;
        const debounceTime = parseInt(
          actionEl.getAttribute("p-debounce") || "0",
        );

        let timer;
        actionEl.addEventListener("keydown", (e) => {
          if (isEnterOnly && e.key !== "Enter") return;
          clearTimeout(timer);
          timer = setTimeout(() => {
            this.callComponentAction(
              el,
              "keydown",
              action,
              this.getInputValue(actionEl),
            );
          }, debounceTime);
        });
      });

      // p-input (real-time input tracking)
      this.filterToBound(el, "[p-input]").forEach((actionEl) => {
        if (actionEl._plugBoundInput) return;
        actionEl._plugBoundInput = true;
        const action = actionEl.getAttribute("p-input");
        const debounceTime = parseInt(
          actionEl.getAttribute("p-debounce") || "300",
        );

        let timer;
        actionEl.addEventListener("input", () => {
          clearTimeout(timer);
          timer = setTimeout(() => {
            this.callComponentAction(
              el,
              "input",
              action,
              this.getInputValue(actionEl),
            );
          }, debounceTime);
        });
      });

      // p-intersect
      this.filterToBound(el, "[p-intersect]").forEach((actionEl) => {
        if (actionEl._plugBoundIntersect) return;
        actionEl._plugBoundIntersect = true;
        const action = actionEl.getAttribute("p-intersect");
        const observer = new IntersectionObserver((entries) => {
          if (entries[0].isIntersecting) {
            this.callComponentAction(el, "intersect", action);
            observer.disconnect();
          }
        });
        observer.observe(actionEl);
      });

      // p-model (data binding)
      this.filterToBound(el, "[p-model]").forEach((actionEl) => {
        if (actionEl._plugBoundModel) return;
        actionEl._plugBoundModel = true;
        const action = actionEl.getAttribute("p-model");
        const debounceTime = parseInt(
          actionEl.getAttribute("p-debounce") || "300",
        );

        let timer;
        actionEl.addEventListener("input", () => {
          clearTimeout(timer);
          timer = setTimeout(() => {
            this.callComponentAction(
              el,
              "model",
              action,
              this.getInputValue(actionEl),
            );
          }, debounceTime);
        });
      });

      // p-stream (Server-Sent Events)
      this.filterToBound(el, "[p-stream]").forEach((actionEl) => {
        if (actionEl._plugBoundStream) return;
        actionEl._plugBoundStream = true;
        
        const streamAttr = actionEl.getAttribute("p-stream");
        const match = streamAttr.match(/(.*?)\s+as\s+(.*)/);
        if(!match) return;
        
        const url = match[1].trim();
        const stateVar = match[2].trim();
        
        const source = new EventSource(url);
        source.onmessage = (event) => {
          try {
            const data = JSON.parse(event.data);
            if (el._pState) {
              el._pState[stateVar] = data;
              if (actionEl.hasAttribute("p-updated")) {
                this.runAction(actionEl.getAttribute("p-updated"), {
                  $el: actionEl,
                  ...(el._pState || {})
                });
              }
            }
          } catch(e) {
            // Silent fail
          }
        };
        
        actionEl._plugEventSource = source;
      });

      // p-on:* (Advanced Events)
      const isPlugComp = el.hasAttribute("data-plug-component");
      const eventEls = [el, ...Array.from(el.querySelectorAll("*"))].filter(
        (child) => child.closest("[data-plug-component], [p-data]") === el,
      );

      eventEls.forEach((actionEl) => {
        Array.from(actionEl.attributes).forEach((attr) => {
          if (attr.name.startsWith("p-on:")) {
            if (actionEl._plugHandlers && actionEl._plugHandlers[attr.name])
              return;
            if (!actionEl._plugHandlers) actionEl._plugHandlers = {};

            const parts = attr.name.slice(5).split(".");
            const eventName = parts[0];
            const modifiers = parts.slice(1);
            const expression = attr.value;

            const handler = (e) => {
              if (modifiers.includes("prevent")) e.preventDefault();
              if (modifiers.includes("stop")) e.stopPropagation();
              if (modifiers.includes("self") && e.target !== actionEl) return;
              if (modifiers.includes("outside") && actionEl.contains(e.target))
                return;

              if (modifiers.includes("window") || modifiers.includes("document"))
                return;

              const context = {
                ...(el._pState || {}),
                $el: actionEl,
                $event: e,
                $dispatch: (name, detail = {}) =>
                  window.dispatchEvent(
                    new CustomEvent(name, { detail, bubbles: true }),
                  ),
              };

              if (isPlugComp && (!el._pState || !el._pState[expression.split("(")[0]])) {
                this.callComponentAction(el, eventName, expression);
              } else {
                this.runAction(expression, context);
              }
            };

            actionEl._plugHandlers[attr.name] = handler;

            if (modifiers.includes("window")) {
              window.addEventListener(eventName, handler);
            } else if (modifiers.includes("document")) {
              document.addEventListener(eventName, handler);
            } else {
              actionEl.addEventListener(eventName, handler);
            }
          }
        });
      });

      // Lifecycle: p-mounted
      const mountedAction = el.getAttribute("p-mounted");
      if (mountedAction && !el._pMounted) {
        el._pMounted = true;
        this.runAction(mountedAction, { $el: el, ...(el._pState || {}) });
      }

      el._plugInitialized = true;
    });

    this.observeLinks(container);
  }

  // ─── Async Components ────────────────────────────────────────

  initializeAsyncComponents(container = document) {
    container.querySelectorAll(".plugs-async-component").forEach(async (el) => {
      if (el._asyncInitialized) return;
      el._asyncInitialized = true;

      const src = el.dataset.plugsAsyncSrc;
      const payload = el.dataset.plugsAsyncPayload;

      try {
        let response;
        if (src) {
          response = await fetch(src, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });
        } else if (payload) {
          const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.content;
          response = await fetch(this.getBaseUrl() + "/_plugs/component/render", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify({ payload }),
          });
        }

        if (response && response.ok) {
          const html = await response.text();
          this.morphInner(el, html);
          this.initializeComponents(el);
        }
      } catch (e) {
        console.error("Async Component Error:", e);
      }
    });
  }

  // ─── Fetch Components ────────────────────────────────────────

  initializeFetchComponents(container = document) {
    container.querySelectorAll(".plugs-fetch-component").forEach(async (el) => {
      if (el._fetchInitialized) return;
      el._fetchInitialized = true;

      const url = el.dataset.plugsFetchUrl;
      const templateEl = el.querySelector(".plugs-fetch-success-template");

      if (!url || !templateEl) return;

      try {
        const response = await fetch(url, {
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        });

        if (response.ok) {
          const data = await response.json();
          const template = templateEl.innerHTML;
          const html = this.renderTemplate(template, data);

          this.morphInner(el, html);
          this.initializeComponents(el);
        }
      } catch (e) {
        console.error("Fetch Component Error:", e);
      }
    });
  }

  // ─── Lazy Components (Integrated) ────────────────────────────

  /**
   * Lazy-load components using IntersectionObserver.
   * Components with class `plugs-lazy-component` are observed and loaded
   * when they enter the viewport. This replaces the old plugs-lazy.js file.
   */
  initializeLazyComponents(container = document) {
    // Create observer once and reuse it
    if (!this._lazyObserver) {
      this._lazyObserver = new IntersectionObserver(
        (entries, obs) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const placeholder = entry.target;
              this._loadLazyComponent(placeholder);
              obs.unobserve(placeholder);
            }
          });
        },
        { root: null, rootMargin: "50px", threshold: 0.1 },
      );
    }

    container.querySelectorAll(".plugs-lazy-component").forEach((el) => {
      if (!el._lazyObserved) {
        el._lazyObserved = true;
        this._lazyObserver.observe(el);
      }
    });
  }

  async _loadLazyComponent(el) {
    if (el._plugsLoading) return;
    el._plugsLoading = true;

    const payload = el.dataset.plugsLazyPayload;
    if (!payload) return;

    try {
      window.dispatchEvent(
        new CustomEvent("plugs:lazy-loading", { detail: { el } }),
      );

      const csrfToken = document.querySelector(
        'meta[name="csrf-token"]',
      )?.content;

      const response = await fetch(
        this.getBaseUrl() + "/_plugs/component/render",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken,
          },
          body: JSON.stringify({ payload }),
        },
      );

      if (!response.ok) {
        let errorMessage = `HTTP error! status: ${response.status}`;
        try {
          const errorData = await response.json();
          if (errorData && errorData.error) {
            errorMessage = errorData.error;
          }
        } catch (e) {
          // Fallback to status
        }
        throw new Error(errorMessage);
      }

      const html = await response.text();

      const temp = document.createElement("div");
      temp.innerHTML = html.trim();

      const fragment = document.createDocumentFragment();
      while (temp.firstChild) {
        fragment.appendChild(temp.firstChild);
      }

      const newElements = Array.from(fragment.childNodes).filter(
        (node) => node.nodeType === Node.ELEMENT_NODE,
      );
      const firstNewEl = newElements[0];

      el.replaceWith(fragment);

      if (firstNewEl) {
        newElements.forEach((node) => this.initializeComponents(node));
      }

      window.dispatchEvent(
        new CustomEvent("plugs:lazy-loaded", { detail: { el: firstNewEl } }),
      );
    } catch (error) {
      console.error("Error loading lazy component:", error);
      el.innerHTML = `
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-100 dark:bg-rose-900/20 dark:border-rose-800 text-rose-600 dark:text-rose-400 text-xs font-bold flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Component Error
            </div>
            <div class="text-[10px] opacity-70 ml-6">${error.message}</div>
        </div>
      `;
      window.dispatchEvent(
        new CustomEvent("plugs:lazy-error", { detail: { error, el } }),
      );
    }
  }

  // ─── Template Rendering ──────────────────────────────────────

  renderTemplate(template, data) {
    let html = template;

    html = html.replace(
      /@for\s+(\w+)\s+in\s+([\w.]+)([\s\S]*?)@endfor/g,
      (match, item, listKey, body) => {
        const list = this.getNestedValue(data, listKey) || [];
        if (!Array.isArray(list)) return "";
        return list
          .map((val) => {
            const context = { ...data, [item]: val };
            return this.renderTemplate(body, context);
          })
          .join("");
      },
    );

    html = html.replace(
      /@if\s*\((.*?)\)([\s\S]*?)@endif/g,
      (match, condition, body) => {
        const val = this.getNestedValue(data, condition.trim());
        return val ? this.renderTemplate(body, data) : "";
      },
    );

    html = html.replace(/\{\{?\s*(.*?)\s*\}?\}/g, (match, key) => {
      return this.getNestedValue(data, key) ?? "";
    });

    return html;
  }

  getNestedValue(obj, path) {
    if (!path || path === "data") return obj;
    return path.split(".").reduce((acc, part) => acc && acc[part], obj);
  }

  // ─── Outside Click Handler ───────────────────────────────────

  handleOutsideClick(e) {
    if (e.defaultPrevented) return;
    document.querySelectorAll("[p-outside]").forEach((actionEl) => {
      if (!actionEl.contains(e.target)) {
        const componentEl = actionEl.closest("[data-plug-component]");
        if (!componentEl) return;
        if (componentEl._isProcessingQueue || componentEl._isPolling) return;

        this.callComponentAction(
          componentEl,
          "outside",
          actionEl.getAttribute("p-outside"),
        );
      }
    });
  }

  // ─── DOM Morphing Engine ─────────────────────────────────────

  morphInner(el, newHTML) {
    const temp = document.createElement("div");
    temp.innerHTML = newHTML.trim();
    const newEl = temp.firstElementChild;

    if (el.hasAttribute('data-plug-component')) {
      this._morphChildNodes(el, temp);
    } else if (newEl && temp.childElementCount === 1 && (newEl.tagName === el.tagName)) {
      this._morphElement(el, newEl);
    } else {
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
        if (node.nodeType === 1) {
          const unmountAction = node.getAttribute("p-unmounted");
          if (unmountAction) {
            this.runAction(unmountAction, { $el: node });
          }
          node.querySelectorAll("[p-unmounted]").forEach((child) => {
            this.runAction(child.getAttribute("p-unmounted"), { $el: child });
          });
        }
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

    if (!PlugsSPA._voidElements.has(oldEl.tagName)) {
      this._morphChildNodes(oldEl, newEl);
    }
  }

  // ─── End DOM Morphing Engine ─────────────────────────────────

  /**
   * Fast HTML comparison — avoids creating DOM elements when possible.
   */
  normalizeHTML(html) {
    const div = document.createElement("div");
    div.innerHTML = html.trim();
    return div.innerHTML.replace(/\s+/g, " ");
  }

  getInputValue(el) {
    if (el.type === "checkbox") return el.checked;
    if (el.type === "radio") return el.checked ? el.value : null;
    if (el.tagName === "SELECT" && el.multiple) {
      return Array.from(el.selectedOptions).map((opt) => opt.value);
    }
    return el.value;
  }

  // ─── Component Actions ───────────────────────────────────────

  async callComponentAction(element, eventType, action, payload = null) {
    const componentEl = element.closest("[data-plug-component]");
    if (!componentEl) return;

    if (action.includes("++")) {
      const prop = action.replace("++", "").trim();
      action = "increment";
      payload = prop;
    } else if (action.includes("--")) {
      const prop = action.replace("--", "").trim();
      action = "decrement";
      payload = prop;
    } else if (action.includes("=") && !action.includes("(")) {
      const parts = action.split("=");
      const prop = parts[0].trim();
      let val = parts[1].trim();
      if (
        (val.startsWith("'") && val.endsWith("'")) ||
        (val.startsWith('"') && val.endsWith('"'))
      ) {
        val = val.substring(1, val.length - 1);
      }
      if (val === "true") val = true;
      else if (val === "false") val = false;
      else if (val === "null") val = null;
      else if (!isNaN(val) && val !== "") val = Number(val);

      action = "updateProperty";
      payload = [prop, val];
    }

    if (!componentEl._requestQueue) componentEl._requestQueue = [];

    // Deduplication Logic
    if (eventType === "poll") {
      if (componentEl._requestQueue.some((r) => r.eventType === "poll")) return;
    } else if (
      eventType === "keyup" ||
      eventType === "keydown" ||
      eventType === "input"
    ) {
      const existing = componentEl._requestQueue.find(
        (r) =>
          r.eventType === "keyup" ||
          r.eventType === "keydown" ||
          r.eventType === "input",
      );
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

    const loadingSelector = componentEl.getAttribute("p-loading");
    let loadingEls = [];
    const showLoading = ["click", "submit"].includes(eventType);
    if (loadingSelector && showLoading) {
      loadingEls = this.filterToBound(componentEl, loadingSelector);
      loadingEls.forEach((el) => (el.style.display = ""));
      componentEl.classList.add("p-is-loading");
    }

    try {
      const response = await fetch(this.getBaseUrl() + "/plugs/component/action", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN":
            document.querySelector('meta[name="csrf-token"]')?.content || "",
        },
        body: JSON.stringify({ component: name, action, state, id, payload }),
      });

      if (!response.ok) throw new Error("Action failed");

      this.handleFlashMessage(response);
      const result = await response.json();

      if (result.html !== undefined) {
        // Fast-path: skip expensive normalizeHTML if raw strings match
        const trimmedNew = result.html.trim();
        const trimmedOld = componentEl.innerHTML.trim();

        if (trimmedNew !== trimmedOld) {
          const normalizedNew = this.normalizeHTML(result.html);
          const normalizedOld = this.normalizeHTML(componentEl.innerHTML);

          if (normalizedNew !== normalizedOld) {
            this.morphInner(componentEl, result.html);
          }
        }
      }

      if (result.state) componentEl.dataset.plugState = result.state;

      if (result.events && Array.isArray(result.events)) {
        result.events.forEach((evt) => {
          const detail = evt.params || evt.detail || {};
          window.dispatchEvent(new CustomEvent(evt.event, { detail, bubbles: true }));
        });
      }

      this.initializeComponents(componentEl);
    } catch (err) {
      console.error("Plugs Component Error:", err);
    } finally {
      if (showLoading && loadingSelector) {
        loadingEls.forEach((el) => (el.style.display = "none"));
        componentEl.classList.remove("p-is-loading");
      }
      this.processRequestQueue(componentEl);
    }
  }

  // ─── Link / Form Handling ────────────────────────────────────

  handleLinkClick(e) {
    const link = e.target.closest("a");
    if (
      !link ||
      !this.isInternalLink(link) ||
      link.dataset.spa !== "true" ||
      link.target === "_blank"
    )
      return;

    const confirmMsg = link.getAttribute("p-confirm");
    if (confirmMsg && !confirm(confirmMsg)) {
      e.preventDefault();
      return;
    }

    e.preventDefault();
    const target =
      link.getAttribute("data-spa-target") || this.options.contentSelector;
    const method = (link.getAttribute("p-method") || "GET").toUpperCase();

    this.navigate(link.href, true, target, method !== "GET" ? { method } : {});
  }

  handleFormSubmit(e) {
    const form = e.target;
    if (form.dataset.spa !== "true") return;

    const action = form.getAttribute("action") || window.location.href;
    const actionUrl = new URL(action, window.location.origin);
    if (actionUrl.host !== window.location.host) return;

    e.preventDefault();
    const method = (form.getAttribute("method") || "GET").toUpperCase();
    const target =
      form.getAttribute("data-spa-target") || this.options.contentSelector;

    this.navigate(action, true, target, {
      method,
      body: method === "GET" ? null : new FormData(form),
    });
  }

  async load(url, targetSelector) {
    return this.navigate(url, false, targetSelector);
  }

  // ─── Progress Bar ────────────────────────────────────────────

  showProgress() {
    this.progressBar.style.opacity = "1";
    this.progressBar.style.width = "30%";
    this.progressTimer = setTimeout(() => {
      this.progressBar.style.width = "70%";
    }, 300);
  }

  hideProgress() {
    clearTimeout(this.progressTimer);
    this.progressBar.style.width = "100%";
    setTimeout(() => {
      this.progressBar.style.opacity = "0";
      setTimeout(() => {
        this.progressBar.style.width = "0";
      }, 300);
    }, 200);
  }

  // ─── Prefetching ─────────────────────────────────────────────

  handleLinkHover(e) {
    const link = e.target.closest("a");
    if (
      !link ||
      !this.isInternalLink(link) ||
      link.dataset.spa !== "true" ||
      this.cache.has(link.href)
    )
      return;

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
        headers: {
          "X-Plugs-SPA": "true",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      if (response.ok) {
        const html = await response.text();
        this.cache.set(url, html);
        this.cacheTimestamps.set(url, Date.now());
        this.cleanCache();
      }
    } catch (e) {}
  }

  isInternalLink(link) {
    return link.host === window.location.host;
  }

  handleFlashMessage(response) {
    const flash = response.headers.get("X-Plugs-Flash");
    if (flash) {
      try {
        const message = JSON.parse(flash);
        window.dispatchEvent(
          new CustomEvent("plugs:flash", { detail: message }),
        );
      } catch (e) {}
    }
  }

  // ─── SPA Navigation ─────────────────────────────────────────

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

      const styleFragments = Array.from(
        doc.querySelectorAll('style, link[rel="stylesheet"]'),
      );
      const scriptFragments = Array.from(doc.querySelectorAll("script"));

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
          } catch (e) {}
        }
        this.currentView = null;

        document
          .querySelectorAll("[data-spa-injected]")
          .forEach((el) => el.remove());

        contentArea.innerHTML = cleanHTML;

        styleFragments.forEach((el) => {
          el.setAttribute("data-spa-injected", "true");
          document.head.appendChild(el);
        });

        const existingScripts = Array.from(document.querySelectorAll("script"));
        scriptFragments.forEach((oldScript) => {
          if (oldScript.hasAttribute("data-spa-ignore")) return;
          if (oldScript.src && existingScripts.some(s => s.src === oldScript.src)) return;
          if (!oldScript.src && oldScript.innerHTML && existingScripts.some(s => s.innerHTML === oldScript.innerHTML)) return;

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

        this.initializeComponents(contentArea);

        if (pushState && isMainContent) {
          window.history.pushState({ spa: true }, "", url);
          window.scrollTo(0, 0);
        }

        this.options.onComplete(url);
        document.dispatchEvent(new CustomEvent('plugs:spa:load', {
            detail: { url }
        }));

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

  // ─── Skeleton Placeholders ───────────────────────────────────

  getSkeletonPlaceholder(type) {
    const shimmer =
      '<div class="plugs-skeleton" style="width: 100%; height: 20px; border-radius: 4px; margin-bottom: 10px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite;"></div>';

    if (!document.getElementById("plugs-skeleton-style")) {
      const style = document.createElement("style");
      style.id = "plugs-skeleton-style";
      style.innerHTML = `@keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }`;
      document.head.appendChild(style);
    }

    switch (type) {
      case "card":
        return `<div class="p-4 border rounded-lg">${shimmer.repeat(4)}</div>`;
      case "list":
        return `<div class="space-y-4">${shimmer.repeat(5)}</div>`;
      default:
        return shimmer.repeat(3);
    }
  }
}

// ─── Static Properties (O(1) lookups) ────────────────────────

PlugsSPA._voidElements = new Set([
  "AREA", "BASE", "BR", "COL", "EMBED", "HR", "IMG",
  "INPUT", "LINK", "META", "SOURCE", "TRACK", "WBR",
]);

// ─── Auto-Initialize ────────────────────────────────────────

if (!window.plugsSPA) {
  window.plugsSPA = new PlugsSPA();
  window.Plugs = window.plugsSPA;
}
