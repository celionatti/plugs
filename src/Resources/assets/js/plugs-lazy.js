document.addEventListener("DOMContentLoaded", function () {
  const observerOptions = {
    root: null,
    rootMargin: "0px",
    threshold: 0.1,
  };

  const observer = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const placeholder = entry.target;
        loadComponent(placeholder);
        observer.unobserve(placeholder);
      }
    });
  }, observerOptions);

  function scanForLazyComponents() {
    document.querySelectorAll(".plugs-lazy-component").forEach((el) => {
      observer.observe(el);
    });
  }

  async function loadComponent(el) {
    if (el._plugsLoading) return;
    el._plugsLoading = true;

    const payload = el.dataset.plugsLazyPayload; // Assuming original dataset key `plugsLazyPayload` is kept
    // const name = el.dataset.lazyName; // This variable was in the snippet but not used, keeping original logic

    if (!payload) return;

    const getBaseUrl = () => {
      const meta = document.querySelector('meta[name="app-url"]');
      if (meta) {
        return meta.content.endsWith('/') ? meta.content.slice(0, -1) : meta.content;
      }
      return '';
    };

    try {
      window.dispatchEvent(new CustomEvent("plugs:lazy-loading", { detail: { el: el } }));

      // Get CSRF token
      const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.content; 

      const baseUrl = getBaseUrl();
      const response = await fetch(baseUrl + "/_plugs/component/render", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({ payload: payload }),
      });

      if (!response.ok) {
        let errorMessage = `HTTP error! status: ${response.status}`;
        try {
          const errorData = await response.json();
          if (errorData && errorData.error) {
            errorMessage = errorData.error;
          }
        } catch (e) {
          // Fallback to status if JSON parsing fails
        }
        throw new Error(errorMessage);
      }

      const html = await response.text();

      // Swap content
      const temp = document.createElement("div");
      temp.innerHTML = html.trim();
      
      const fragment = document.createDocumentFragment();
      while (temp.firstChild) {
          fragment.appendChild(temp.firstChild);
      }
      
      const newElements = Array.from(fragment.childNodes).filter(node => node.nodeType === Node.ELEMENT_NODE);
      const firstNewEl = newElements[0];
      
      el.replaceWith(fragment);

      // Re-initialize with SPA engine if available
      if (window.plugsSPA && firstNewEl) {
          newElements.forEach(node => window.plugsSPA.initializeComponents(node));
      }

      window.dispatchEvent(new CustomEvent("plugs:lazy-loaded", { detail: { el: firstNewEl } }));
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
      window.dispatchEvent(new CustomEvent("plugs:lazy-error", { detail: { error, el: el } }));
    }
  }

  // Initial scan
  scanForLazyComponents();
});
