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
      placeholder.outerHTML = html;

      // Re-scan for nested lazy components or other directives if needed
      // (If the loaded content contains script tags, they won't execute by default with innerHTML/outerHTML
      //  unless we explicitly handle them, but standard HTML components should be fine)
    } catch (error) {
      console.error("Error loading lazy component:", error);
      placeholder.innerHTML =
        '<div class="text-danger p-2">Error loading component</div>';
    }
  }

  // Initial scan
  scanForLazyComponents();
});
