<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Utils\Minifier;

class MakeEditorAssetCommand extends Command
{
    protected string $description = 'Create the plugs-editor.js and plugs-editor.css files in public plugs directory';

    public function handle(): int
    {
        $this->title('Editor Asset Generator');

        $directory = getcwd() . '/public/plugs';
        $jsFilename = 'plugs-editor.js';
        $cssFilename = 'plugs-editor.css';

        $shouldMinify = $this->hasOption('min');
        $force = $this->isForce();

        // Ensure directory exists
        if (!$this->ensureDirectory($directory)) {
            $this->error("Failed to create directory: {$directory}");
            return 1;
        }

        $this->section('Generating Assets');

        // JS
        $jsPath = $directory . '/' . $jsFilename;
        $jsContent = $this->getJsContent();
        $this->writeAsset($jsPath, $jsFilename, $jsContent, $force, $shouldMinify, 'js');

        // CSS
        $cssPath = $directory . '/' . $cssFilename;
        $cssContent = $this->getCssContent();
        $this->writeAsset($cssPath, $cssFilename, $cssContent, $force, $shouldMinify, 'css');

        $this->newLine();
        $this->info("Editor assets installed successfully.");
        $this->info("The RichTextField component will now use these assets from /plugs/");
        $this->newLine();

        return 0;
    }

    protected function writeAsset(string $path, string $filename, string $content, bool $force, bool $shouldMinify, string $type): void
    {
        if (file_exists($path) && !$force) {
            if (!$this->confirm("File {$filename} already exists. Overwrite?", false)) {
                $this->warning("Skipped: {$filename}");
            } else {
                file_put_contents($path, $content);
                $this->success("Updated: public/plugs/{$filename}");
            }
        } else {
            file_put_contents($path, $content);
            $this->success("Created: public/plugs/{$filename}");
        }

        if ($shouldMinify) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $minFilename = str_replace(".{$ext}", ".min.{$ext}", $filename);
            $minPath = dirname($path) . '/' . $minFilename;

            $minified = $type === 'js' ? Minifier::js($content) : Minifier::css($content);
            file_put_contents($minPath, $minified);
            $this->success("Created: public/plugs/{$minFilename}");
        }
    }

    protected function defineOptions(): array
    {
        return [
            '--min' => 'Create minified versions',
            '--force' => 'Overwrite existing files',
        ];
    }

    private function getJsContent(): string
    {
        return <<<'JS'
/**
 * Plugs Rich Text Editor Wrapper
 * Integrates Quill with automatic image uploading.
 */
class PlugsEditor {
  constructor(containerId, hiddenInputId, options = {}) {
    this.container = document.getElementById(containerId);
    this.hiddenInput = document.getElementById(hiddenInputId);
    this.uploadUrl = options.uploadUrl || "/plugs/media/upload";

    // Image constraints
    this.maxWidth = this.container.dataset.maxWidth || null;
    this.maxHeight = this.container.dataset.maxHeight || null;

    this.quill = new Quill(this.container, {
      theme: "snow",
      modules: {
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
        },
      },
      placeholder: options.placeholder || "Compose something epic...",
      ...options,
    });

    // Sync content with hidden input
    this.quill.on("text-change", () => {
      this.hiddenInput.value = this.quill.root.innerHTML;
    });

    // Initialize with existing value
    if (this.hiddenInput.value) {
      this.quill.root.innerHTML = this.hiddenInput.value;
    }

    // Add drop support for images
    this.setupDropSupport();

    // Initialize tooltips
    this.initTooltips();
  }

  imageHandler() {
    const input = document.createElement("input");
    input.setAttribute("type", "file");
    input.setAttribute("accept", "image/*");
    input.click();

    input.onchange = async () => {
      const file = input.files[0];
      if (file) {
        await this.uploadImage(file);
      }
    };
  }

  async uploadImage(file) {
    const formData = new FormData();
    formData.append("image", file);

    // Show loading state
    this.setLoading(true);

    try {
      let uploadUrl = this.uploadUrl;
      const params = new URLSearchParams();
      if (this.maxWidth) params.append("max_width", this.maxWidth);
      if (this.maxHeight) params.append("max_height", this.maxHeight);

      if (params.toString()) {
        uploadUrl += (uploadUrl.includes("?") ? "&" : "?") + params.toString();
      }

      const response = await fetch(uploadUrl, {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      const result = await response.json();

      if (result.url) {
        const range = this.quill.getSelection(true);
        this.quill.insertEmbed(range.index, "image", result.url);
        this.quill.setSelection(range.index + 1);
      } else {
        alert(result.error || "Upload failed");
      }
    } catch (error) {
      console.error("Editor upload error:", error);
      alert("An unexpected error occurred during upload.");
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

  setupDropSupport() {
    this.quill.root.addEventListener(
      "drop",
      (e) => {
        e.preventDefault();
        if (
          e.dataTransfer &&
          e.dataTransfer.files &&
          e.dataTransfer.files.length
        ) {
          const file = e.dataTransfer.files[0];
          if (file.type.match(/^image\//)) {
            this.uploadImage(file);
          }
        }
      },
      false,
    );
  }

  initTooltips() {
    const tooltipMap = {
      "ql-header": "Heading",
      "ql-bold": "Bold",
      "ql-italic": "Italic",
      "ql-underline": "Underline",
      "ql-strike": "Strike",
      "ql-blockquote": "Quote",
      "ql-code-block": "Code Block",
      "ql-list[value='ordered']": "Ordered List",
      "ql-list[value='bullet']": "Bullet List",
      "ql-link": "Insert Link",
      "ql-image": "Insert Image",
      "ql-clean": "Clear Formatting",
    };

    const toolbar = this.container.previousElementSibling;
    if (toolbar) {
      for (const [selector, text] of Object.entries(tooltipMap)) {
        const elements = toolbar.querySelectorAll(`.${selector}`);
        elements.forEach((el) => {
          el.setAttribute("title", text);
        });
      }
    }
  }
}

// Auto-init for data-plugs-editor
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-plugs-editor]").forEach((el) => {
    const containerId = el.id;
    const hiddenInputId = el.dataset.plugsEditor;
    new PlugsEditor(containerId, hiddenInputId);
  });
});
JS;
    }

    private function getCssContent(): string
    {
        return <<<'CSS'
/* Rich Text Editor Styles */
.plugs-editor-wrapper {
  position: relative;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  overflow: hidden;
  background: #fff;
  width: 100%;
  transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.plugs-editor-wrapper:focus-within {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.plugs-editor-container {
  min-height: 250px;
  font-family: inherit;
  font-size: 1rem;
}

.ql-toolbar.ql-snow {
  border: none !important;
  border-bottom: 1px solid #dee2e6 !important;
  background: #f8f9fa;
  display: flex !important;
  flex-wrap: wrap;
}

.ql-container.ql-snow {
  border: none !important;
}

.ql-editor {
  min-height: 250px;
  padding: 1.25rem;
}

/* Ensure images inside the editor are responsive */
.ql-editor img {
  max-width: 100%;
  height: auto;
  border-radius: 4px;
  display: block;
  margin: 1rem 0;
}

.ql-editor h1,
.ql-editor h2,
.ql-editor h3,
.ql-editor h4,
.ql-editor h5,
.ql-editor h6 {
  margin-top: 1.5rem;
  margin-bottom: 1rem;
  font-weight: 600;
  line-height: 1.2;
}

.ql-editor p {
  margin-bottom: 1rem;
}

.ql-editor blockquote {
  border-left: 4px solid #0d6efd;
  padding-left: 1rem;
  margin: 1.5rem 0;
  color: #6c757d;
  font-style: italic;
}

.plugs-editor-uploading {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(2px);
}

.plugs-editor-uploading::after {
  content: "Uploading Image...";
  font-weight: 600;
  color: #0d6efd;
  animation: ql-pulse 1.5s infinite;
}

@keyframes ql-pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}
CSS;
    }
}
