# SPA & REACTIVE EXAMPLES

This guide provides practical, real-world examples of how to combine the SPA Bridge and Reactive Components to build modern interfaces.

---

## 1. The Interactive Counter

The simplest example of a reactive component.

### PHP Component (`app/Components/Counter.php`)
```php
namespace App\Components;
use Plugs\View\ReactiveComponent;

class Counter extends ReactiveComponent {
    public int $count = 0;
    public function increment() { $this->count++; }
    public function decrement() { $this->count--; }
    public function render() { return 'components.counter'; }
}
```

### View Template (`resources/views/components/counter.plug.php`)
```html
<div class="flex items-center gap-4 p-4 bg-white shadow rounded-lg">
    <button p-click="decrement" class="btn btn-red">-</button>
    <span class="text-2xl font-bold">{{ $count }}</span>
    <button p-click="increment" class="btn btn-green">+</button>
</div>
```

---

## 2. Real-time Search with SPA Bridge

Using the SPA Bridge to filter a list without a page reload.

### Controller
```php
public function index(Request $request) {
    if ($request->header('X-Plugs-SPA')) {
        // Return only the partial view if it's an SPA request
        return view('users.list', [
            'users' => User::search($request->query('q'))->get()
        ]);
    }
    return view('users.index', ['users' => User::all()]);
}
```

### Main View (`resources/views/users/index.plug.php`)
```html
<div class="p-6">
    <form action="/users" method="GET" data-spa="true" data-spa-target="#user-list">
        <input type="text" name="q" placeholder="Search users..." p-change="submit">
    </form>

    <div id="user-list">
        @include('users.list')
    </div>
</div>
```

---

## 3. Dynamic Form Validation

Using a Reactive Component to provide instant feedback.

### PHP Component (`app/Components/RegistrationForm.php`)
```php
class RegistrationForm extends ReactiveComponent {
    public string $email = '';
    public string $emailError = '';

    public function updatedEmail() {
        if (User::where('email', $this->email)->exists()) {
            $this->emailError = "This email is already taken!";
        } else {
            $this->emailError = "";
        }
    }

    public function render() { return 'components.reg-form'; }
}
```

### View Template (`resources/views/components/reg-form.plug.php`)
```html
<div class="form-group">
    <label>Email Address</label>
    <input type="email" name="email" p-change="updatedEmail" value="{{ $email }}">
    @if($emailError)
        <p class="text-red-500">{{ $emailError }}</p>
    @endif
</div>
```

---

## Tips for Success

1.  **Keep State Small**: Only store data in public properties that you actually need for the UI.
2.  **Use `data-spa-target`**: For large pages, only update the specific section that changed to save bandwidth and improve speed.
3.  **Client-side Events**: Use the `bolt:updated` JavaScript event to trigger animations or third-party libraries after a component re-renders.

```javascript
document.addEventListener('bolt:updated', (e) => {
    console.log('Component updated:', e.detail.componentId);
    // Initialize tooltips, etc.
});
```
