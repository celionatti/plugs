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

  async function loadComponent(placeholder) {
    const payload = placeholder.dataset.plugsLazyPayload;

    if (!payload) return;

    try {
      window.dispatchEvent(new CustomEvent("plugs:lazy-loading", { detail: { el: placeholder } }));

      // Get CSRF token
      const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

      const response = await fetch("/_plugs/component/render", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({ payload: payload }),
      });

      if (!response.ok) {
        throw new Error("Network response was not ok");
      }

      const html = await response.text();

      // Swap content
      const temp = document.createElement("div");
      temp.innerHTML = html.trim();
      const newEl = temp.firstElementChild;
      placeholder.replaceWith(newEl);

      // Re-initialize with SPA engine if available
      if (window.plugsSPA) {
        window.plugsSPA.initializeComponents(newEl);
      }

      window.dispatchEvent(new CustomEvent("plugs:lazy-loaded", { detail: { el: newEl } }));
    } catch (error) {
      console.error("Error loading lazy component:", error);
      placeholder.innerHTML =
        '<div class="text-danger p-2">Error loading component</div>';
      window.dispatchEvent(new CustomEvent("plugs:lazy-error", { detail: { error, el: placeholder } }));
    }
  }

  // Initial scan
  scanForLazyComponents();
});
