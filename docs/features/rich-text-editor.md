# Rich Text Editor

PLUGS includes a lightweight, professional rich text editor based on Quill.js. It features automatic image uploads, drag-and-drop support, full-screen writing mode, real-time statistics, and seamless integration with the PLUGS form builder and media system.

## Key Features

- **Rich Formatting**: Support for H1-H6, Bold, Italic, Underline, Strike, Lists (Ordered/Bullet), Quotes, and Code blocks.
- **Advanced Media**: Automatic image uploads via drag-and-drop and support for video embedding (YouTube/Vimeo).
- **Professional UX**:
  - **Full-Screen Mode**: Dedicated toggle for focused, distraction-free writing.
  - **Live Statistics**: Real-time Word and Character counter HUD.
  - **Sticky Toolbar**: The toolbar stays pinned to the top of the viewport for easy access.
  - **Image Resize**: Interactive handles to resize and align images.
- **Image Size Constraints**: Configure maximum width and height for uploaded images.
- **Interactive Tooltips**: Helpful hover descriptions for all toolbar icons.
- **Secure by Default**: Integrates with the PLUGS `Sanitizer` to ensure safe HTML output.

## Installation & Assets

Before using the editor, you must generate or publish the required frontend assets using the framework's CLI:

```bash
php theplugs make:plugs-assets --force
```

**Options:**

- `--min`: Create minified versions (`plugs-editor.min.js`, `plugs-editor.min.css`).
- `--force`: Overwrite existing files.

Assets are published to `public/plugs/`.

---

## Form Builder Usage

The easiest way to use the editor is via the `RichTextField` in the PLUGS `FormBuilder`.

```php
use Plugs\Forms\FormBuilder;
use Plugs\Forms\Fields\RichTextField;

$form = FormBuilder::create('/post/save')
    ->add((new RichTextField('content', 'Post Content'))
        ->placeholder('Write your blog post here...')
        ->maxImageWidth(800)
        ->maxImageHeight(600))
    ->add(new SubmitField('Publish Post'));
```

The component automatically handles script inclusion, CSRF protection, and links the editor to the form submission.

---

## Manual Implementation (without Form Builder)

If you are building a custom UI, you can implement the editor manually.

### 1. Include Assets

Include the Quill library and the PLUGS editor assets in your layout:

```html
<!-- Quill (CDN) -->
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css" />
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<!-- Image Resize Module (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.js"></script>

<!-- PLUGS Editor (Local) -->
<link rel="stylesheet" href="/plugs/plugs-editor.css" />
<script src="/plugs/plugs-editor.js"></script>
```

### 2. HTML Structure

Wrap the editor in a `.plugs-editor-wrapper` and use the `data-plugs-editor` attribute to link the container to a hidden input ID:

```html
<div class="plugs-editor-wrapper">
  <div
    id="editor-container"
    data-plugs-editor="content-input"
    data-placeholder="Type something..."
    data-max-width="1200"
  ></div>

  <input type="hidden" name="content" id="content-input" />
</div>
```

---

## Interactive Features

### 🖥️ Full-Screen Mode

A toggle icon is available in the toolbar to expand the editor to the full viewport. This provides a clean, centered interface for heavy content creation.

### 📊 Content Statistics

The editor includes a "HUD" at the bottom right that displays a real-time word and character count, helping authors track their content length.

### 🖼️ Image Handling

The editor includes the `quill-image-resize-module` by default:

- **Resize**: Click any image to see resize handles.
- **Align**: Use the overlay toolbar to set image alignment (Left, Center, Right).
- **Delete**: Simply **select the image** and press the `Backspace` or `Delete` key on your keyboard.
- **Upload**: Drag-and-drop an image directly into the editor for instant background publishing.

---

## Configuration & Customization

### Image Size Constraints

You can limit the dimensions of uploaded images. The backend will automatically resize images that exceed these limits.

**Form Builder:**

```php
$field->maxImageWidth(1200)->maxImageHeight(800);
```

**Manual HTML:**

```html
<div id="editor" data-max-width="1200" data-max-height="800"></div>
```

---

## Security

PLUGS automatically sanitizes editor content. When retrieving content from the request, it is recommended to use the `Sanitizer::safeHtml()` method for server-side cleaning:

```php
use Plugs\Security\Sanitizer;

$safeHtml = Sanitizer::safeHtml($request->get('content'));
```

This ensures only allowed HTML tags and attributes are preserved, protecting your application from XSS attacks.
