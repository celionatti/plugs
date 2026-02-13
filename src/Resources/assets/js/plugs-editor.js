/**
 * Plugs Rich Text Editor Wrapper
 * Robust Quill integration with automatic image uploading and resizing.
 */

(function() {
    // 1. Module Registration
    // We do this globally and immediately to avoid race conditions.
    function registerModules() {
        if (typeof Quill === 'undefined') return;

        const ImageResizeMod = window.ImageResize || window.QuillResize;
        if (ImageResizeMod) {
            // Handle ES Module default exports from some CDNs
            const constructor = ImageResizeMod.default || ImageResizeMod;
            if (typeof constructor === 'function') {
                try {
                    Quill.register('modules/imageResize', constructor, true);
                } catch (e) {
                    console.warn("Quill module registration warning:", e);
                }
            } else {
                console.error("ImageResize module found but it is not a constructor:", constructor);
            }
        }
    }

    // Attempt registration if Quill is already loaded
    registerModules();

    class PlugsEditor {
        constructor(containerId, hiddenInputId, options = {}) {
            this.container = document.getElementById(containerId);
            this.hiddenInput = document.getElementById(hiddenInputId);
            
            if (!this.container || !this.hiddenInput) {
                console.error("PlugsEditor: Missing required elements", { containerId, hiddenInputId });
                return;
            }

            this.uploadUrl = options.uploadUrl || "/plugs/media/upload";
            this.maxWidth = this.container.dataset.maxWidth || null;
            this.maxHeight = this.container.dataset.maxHeight || null;

            this.init(options);
        }

        init(options) {
            // Re-check registration just in case
            registerModules();

            const defaultModules = {
                toolbar: {
                    container: [
                        [{ header: [1, 2, 3, 4, 5, 6, false] }],
                        ["bold", "italic", "underline", "strike"],
                        ["blockquote", "code-block"],
                        [{ list: "ordered" }, { list: "bullet" }],
                        ["link", "image"],
                        ["clean"],
                    ],
                    handlers: {
                        image: this.imageHandler.bind(this),
                    },
                }
            };

            // Only add imageResize if registered
            if (Quill.imports['modules/imageResize']) {
                defaultModules.imageResize = {
                    displaySize: true,
                    modules: ['DisplaySize', 'Resize', 'Toolbar']
                };
            }

            try {
                this.quill = new Quill(this.container, {
                    theme: "snow",
                    placeholder: options.placeholder || "Compose something epic...",
                    ...options,
                    modules: {
                        ...defaultModules,
                        ...(options.modules || {}),
                    },
                });

                this.setupEvents();
                this.loadInitialContent();
            } catch (error) {
                console.error("PlugsEditor: Failed to initialize Quill", error);
            }
        }

        setupEvents() {
            if (!this.quill) return;

            // Sync with hidden input
            this.quill.on("text-change", () => {
                this.hiddenInput.value = this.quill.root.innerHTML;
            });

            // Drop support
            this.quill.root.addEventListener("drop", (e) => {
                e.preventDefault();
                const files = e.dataTransfer?.files;
                if (files && files.length && files[0].type.match(/^image\//)) {
                    this.uploadImage(files[0]);
                }
            });

            this.initTooltips();
        }

        loadInitialContent() {
            if (this.hiddenInput.value && this.quill) {
                this.quill.root.innerHTML = this.hiddenInput.value;
            }
        }

        imageHandler() {
            const input = document.createElement("input");
            input.setAttribute("type", "file");
            input.setAttribute("accept", "image/*");
            input.click();

            input.onchange = async () => {
                const file = input.files[0];
                if (file) await this.uploadImage(file);
            };
        }

        async uploadImage(file) {
            if (!this.quill) return;

            const formData = new FormData();
            formData.append("image", file);
            this.setLoading(true);

            try {
                let url = this.uploadUrl;
                const params = new URLSearchParams();
                if (this.maxWidth) params.append("max_width", this.maxWidth);
                if (this.maxHeight) params.append("max_height", this.maxHeight);
                if (params.toString()) url += (url.includes("?") ? "&" : "?") + params.toString();

                const response = await fetch(url, {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
                    },
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.error || "Upload failed");

                if (result.url) {
                    const range = this.quill.getSelection(true);
                    this.quill.insertEmbed(range.index, "image", result.url);
                    this.quill.setSelection(range.index + 1);
                }
            } catch (error) {
                console.error("PlugsEditor: Upload error", error);
                alert("Upload failed: " + error.message);
            } finally {
                this.setLoading(false);
            }
        }

        setLoading(loading) {
            const wrapper = this.container.closest(".plugs-editor-wrapper");
            if (!wrapper) return;
            if (loading) {
                const loader = document.createElement("div");
                loader.className = "plugs-editor-uploading";
                wrapper.appendChild(loader);
            } else {
                const loader = wrapper.querySelector(".plugs-editor-uploading");
                if (loader) loader.remove();
            }
        }

        initTooltips() {
            const toolbar = this.container.previousElementSibling;
            if (!toolbar || !toolbar.classList.contains('ql-toolbar')) return;
            
            const tooltips = {
                "ql-header": "Heading", "ql-bold": "Bold", "ql-italic": "Italic",
                "ql-underline": "Underline", "ql-strike": "Strike", "ql-blockquote": "Quote",
                "ql-code-block": "Code Block", "ql-list[value='ordered']": "Ordered List",
                "ql-list[value='bullet']": "Bullet List", "ql-link": "Link",
                "ql-image": "Image", "ql-clean": "Clear"
            };

            Object.entries(tooltips).forEach(([selector, text]) => {
                toolbar.querySelectorAll(`.${selector}`).forEach(el => el.title = text);
            });
        }
    }

    // Expose to window
    window.PlugsEditor = PlugsEditor;

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", autoInit);
    } else {
        autoInit();
    }

    function autoInit() {
        document.querySelectorAll("[data-plugs-editor]").forEach(el => {
            if (!el.dataset.initialized) {
                new PlugsEditor(el.id, el.dataset.plugsEditor);
                el.dataset.initialized = "true";
            }
        });
    }
})();
