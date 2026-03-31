# Premium Notifications

Plugs Framework features a premium notification system that replaces basic alerts with modern, animated glassmorphism components.

---

## 1. Overview

The notification system uses "Glassmorphism" styling, smooth animations, and an auto-dismissing progress bar. It supports the standard `FlashMessage` system in addition to the manual usage of the `Notification` component.

---

## 2. Global Integration

To enable premium notifications throughout your application, add the `@flashPremium` directive to your layout file (e.g., `resources/views/layouts/app.plug.php`).

```html
<!DOCTYPE html>
<html lang="en">
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @yield('content')

    <!-- High-end flash messages -->
    @flashPremium

    <!-- OR: Standard flash messages -->
    @flashBasic
</body>
</html>
```

---

## 3. Usage in Controllers

Triggering a premium notification is done through the standard `FlashMessage` utility.

### Basic Usage

```php
public function store(Request $request) {
    // ... logic ...
    FlashMessage::success('Record saved successfully!');
    return redirect()->back();
}
```

### With Custom Titles & Overrides

```php
FlashMessage::error('Could not delete item.', 'Action Failed');
FlashMessage::info('New feature available!', 'Update');
```

---

## 4. Features & Customization

The `@flashPremium` directive automatically renders each flash message using the `Notification` component.

- **Animations**: Uses 3D transforms for entry/exit.
- **Glassmorphism**: High-blur background with subtle borders.
- **Progress Bar**: Automatically dismisses the notification after its duration (default: 5s).

### Component API (Manual Usage)

If you want to render a notification manually:

```html
<x-notification 
    type="success" 
    title="Custom Title" 
    message="Your message here" 
    duration="8000" 
/>
```

---

## Next Steps
Deep dive into [View Assets](../views/asset-management.md) for further optimization.
