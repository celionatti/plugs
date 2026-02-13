/**
 * Plugs Rich Text Editor Wrapper
 * Robust Quill integration with automatic image uploading, resizing, 
 * character counting, and full-screen mode.
 */

(function() {
    // 1. Module Registration
    function registerModules() {
        if (typeof Quill === 'undefined') return;

        // Image Resize
        const ImageResizeMod = window.ImageResize || window.QuillResize;
        if (ImageResizeMod) {
            const constructor = ImageResizeMod.default || ImageResizeMod;
            if (typeof constructor === 'function') {
                try {
                    Quill.register('modules/imageResize', constructor, true);
                } catch (e) {}
            }
        }
    }

    registerModules();

    class PlugsEditor {
        constructor(containerId, hiddenInputId, options = {}) {
            this.container = document.getElementById(containerId);
            this.hiddenInput = document.getElementById(hiddenInputId);
            
            if (!this.container || !this.hiddenInput) return;

            this.wrapper = this.container.closest('.plugs-editor-wrapper');
            this.uploadUrl = options.uploadUrl || "/plugs/media/upload";
            this.maxWidth = this.container.dataset.maxWidth || null;
            this.maxHeight = this.container.dataset.maxHeight || null;

            this.init(options);
        }

        init(options) {
            registerModules();

            const defaultModules = {
                toolbar: {
                    container: [
                        [{ header: [1, 2, 3, 4, 5, 6, false] }],
                        ["bold", "italic", "underline", "strike"],
                        ["blockquote", "code-block"],
                        [{ list: "ordered" }, { list: "bullet" }],
                        [{ align: [] }, { color: [] }, { background: [] }],
                        ["link", "image", "video"],
                        ["clean", "fullscreen"] // Custom fullscreen button
                    ],
                    handlers: {
                        image: this.imageHandler.bind(this),
                        fullscreen: this.toggleFullScreen.bind(this)
                    },
                }
            };

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

                this.setupUI();
                this.setupEvents();
                this.loadInitialContent();
            } catch (error) {
                console.error("PlugsEditor: Failed to initialize Quill", error);
            }
        }

        setupUI() {
            if (!this.wrapper) return;

            // Add Statistics HUD
            this.statsBar = document.createElement('div');
            this.statsBar.className = 'plugs-editor-stats';
            this.statsBar.innerHTML = 'Words: 0 | Characters: 0';
            this.wrapper.appendChild(this.statsBar);

            // Add Fullscreen icon support
            const fsBtn = this.wrapper.querySelector('.ql-fullscreen');
            if (fsBtn) {
                fsBtn.innerHTML = '<svg viewBox="0 0 18 18"><path class="ql-stroke" d="M11,4h4v4M7,14H3v-4M11,14h4v-4M7,4H3v4"></path></svg>';
                fsBtn.title = 'Toggle Fullscreen';
            }
        }

        setupEvents() {
            if (!this.quill) return;

            this.quill.on("text-change", () => {
                this.hiddenInput.value = this.quill.root.innerHTML;
                this.updateStats();
            });

            this.quill.root.addEventListener("drop", (e) => {
                e.preventDefault();
                const files = e.dataTransfer?.files;
                if (files && files.length && files[0].type.match(/^image\//)) {
                    this.uploadImage(files[0]);
                }
            });

            this.updateStats();
            this.initTooltips();
        }

        updateStats() {
            if (!this.statsBar || !this.quill) return;
            const text = this.quill.getText().trim();
            const charCount = text.length;
            const wordCount = text.length > 0 ? text.split(/\s+/).length : 0;
            this.statsBar.innerHTML = `Words: ${wordCount} | Characters: ${charCount}`;
        }

        toggleFullScreen() {
            if (!this.wrapper) return;
            this.wrapper.classList.toggle('plugs-editor-fullscreen');
            
            if (this.wrapper.classList.contains('plugs-editor-fullscreen')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
            
            this.quill.focus();
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
            if (!this.wrapper) return;
            if (loading) {
                const loader = document.createElement("div");
                loader.className = "plugs-editor-uploading";
                this.wrapper.appendChild(loader);
            } else {
                const loader = this.wrapper.querySelector(".plugs-editor-uploading");
                if (loader) loader.remove();
            }
        }

        initTooltips() {
            const toolbar = this.wrapper?.querySelector('.ql-toolbar');
            if (!toolbar) return;
            
            const tooltips = {
                "ql-header": "Heading", "ql-bold": "Bold", "ql-italic": "Italic",
                "ql-underline": "Underline", "ql-strike": "Strike", "ql-blockquote": "Quote",
                "ql-code-block": "Code Block", "ql-list[value='ordered']": "Ordered List",
                "ql-list[value='bullet']": "Bullet List", "ql-link": "Link",
                "ql-image": "Image", "ql-video": "Video", "ql-clean": "Clear",
                "ql-align": "Alignment", "ql-color": "Text Color", "ql-background": "Background Color"
            };

            Object.entries(tooltips).forEach(([selector, text]) => {
                toolbar.querySelectorAll(`.${selector}`).forEach(el => el.title = text);
            });
        }
    }

    window.PlugsEditor = PlugsEditor;

    function autoInit() {
        document.querySelectorAll("[data-plugs-editor]").forEach(el => {
            if (!el.dataset.initialized) {
                new PlugsEditor(el.id, el.dataset.plugsEditor);
                el.dataset.initialized = "true";
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", autoInit);
    } else {
        autoInit();
    }
})();
