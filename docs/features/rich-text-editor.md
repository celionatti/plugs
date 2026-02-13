# Rich Text Editor

PLUGS includes a lightweight, powerful rich text editor based on Quill.js. It features automatic image uploads, drag-and-drop support, and seamless integration with the PLUGS form builder and media system.

## Key Features

- **Rich Formatting**: Support for H1-H6, Bold, Italic, Lists, Quotes, and Code blocks.
- **Automatic Image Uploads**: Drag-and-drop or select images; they are automatically uploaded to the backend.
- **Image Size Constraints**: Configure maximum width and height for uploaded images.
- **Interactive Tooltips**: Helpful hover descriptions for all toolbar icons.
- **Responsiveness**: Fully responsive layout that fits into any container.
- **Secure by Default**: Integrates with the PLUGS `Sanitizer` to ensure safe HTML output.

## Installation

Before using the editor, you must generate the required frontend assets using the framework's CLI:

```bash
php theplugs make:editor-asset --min
```

This command creates the necessary JS and CSS files in your `public/plugs` directory.

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

The component automatically handles script inclusion and links the editor to the form submission.

---

## Manual Implementation (without Form Builder)

If you are building a custom UI, you can implement the editor manually.

### 1. Include Assets

Include the Quill library and the PLUGS editor assets in your layout:

```html
<!-- Quill (CDN) -->
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css" />
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<!-- Image Resize Module -->
<script src="https://cdn.jsdelivr.net/gh/scrapooo/quill-resize-module@1.0.2/dist/quill-resize-module.js"></script>

<!-- PLUGS Editor (Local) -->
<link rel="stylesheet" href="/plugs/plugs-editor.css" />
<script src="/plugs/plugs-editor.js"></script>
```

### Image Resizing & Alignment

The editor includes the `quill-resize-module`, allowing you to:

- **Click** on an image to select it.
- **Drag corners** to resize the image.
- **Align** the image (Left, Center, Right) using the overlay toolbar.
- **Display Size**: Pixel dimensions are shown while resizing.

This is enabled by default in `plugs-editor.js`.

### 2. HTML Structure

Use the `data-plugs-editor` attribute to link the editor container to a hidden input:

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

### Backend Media Handling

Images are uploaded to `/plugs/media/upload` by default. This route is handled by the `MediaController`, which uses the `FileUploader` service to securely store images in the `public/uploads` directory.

---

## Security

PLUGS automatically sanitizes editor content before it reaches your application logic. When retrieving content, it is recommended to use the `Sanitizer::safeHtml()` method if you need to perform additional server-side cleaning:

```php
use Plugs\Security\Sanitizer;

$safeHtml = Sanitizer::safeHtml($request->get('content'));
```

This ensures that only allowed HTML tags and attributes are preserved, protecting your application from XSS attacks.
