# Plugs View System - Complete Usage Examples

## Table of Contents

1. [Basic Setup](#basic-setup)
2. [Simple Views](#simple-views)
3. [Template Inheritance](#template-inheritance)
4. [Components with Slots](#components-with-slots)
5. [Directives Reference](#directives-reference)
6. [Advanced Examples](#advanced-examples)

---

## Basic Setup

```php
<?php
use Plugs\View\ViewEngine;
use Plugs\View\View;

// Initialize the view engine
$viewEngine = new ViewEngine(
    viewPath: __DIR__ . '/views',
    cachePath: __DIR__ . '/cache/views',
    cacheEnabled: true
);

// Share global data across all views
$viewEngine->share('appName', 'My Application');
$viewEngine->share('currentYear', date('Y'));

// Render a view
$view = new View($viewEngine, 'home', ['title' => 'Welcome']);
echo $view->render();

// Or using method chaining
echo (new View($viewEngine, 'home'))
    ->with('title', 'Welcome')
    ->with('user', $currentUser)
    ->render();
```

---

## Simple Views

### Basic View Template

**File: `views/home.plug.php`**

```php
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
</head>
<body>
    <h1>{{ $title }}</h1>
    
    {{-- This is a comment and won't be rendered --}}
    
    {{-- Escaped output (safe, prevents XSS) --}}
    <p>Welcome, {{ $username }}</p>
    
    {{-- Raw output (unescaped HTML) --}}
    <div>{{{ $htmlContent }}}</div>
    
    {{-- Variables with default values --}}
    <p>{{ $description ?? 'No description available' }}</p>
</body>
</html>
```

### Using the View

```php
<?php
$data = [
    'title' => 'Home Page',
    'username' => '<script>alert("XSS")</script>', // Will be escaped
    'htmlContent' => '<strong>Bold text</strong>' // Won't be escaped
];

echo (new View($viewEngine, 'home', $data))->render();
```

---

## Template Inheritance

### Layout Template

**File: `views/layouts/app.plug.php`**

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Default Title') - {{ $appName }}</title>
    
    {{-- Stack for additional CSS --}}
    @stack('styles')
</head>
<body>
    <header>
        <nav>
            <h1>{{ $appName }}</h1>
        </nav>
    </header>
    
    <main>
        {{-- Main content section --}}
        @yield('content')
    </main>
    
    <footer>
        <p>&copy; {{ $currentYear }} {{ $appName }}</p>
    </footer>
    
    {{-- Stack for additional JavaScript --}}
    @stack('scripts')
</body>
</html>
```

### Child Template Extending Layout

**File: `views/pages/about.plug.php`**

```php
@extends('layouts.app')

@section('title', 'About Us')

@section('content')
    <h2>About Our Company</h2>
    <p>We are a leading company in our field.</p>
    
    @if($showTeam)
        <h3>Our Team</h3>
        <ul>
            @foreach($teamMembers as $member)
                <li>{{ $member['name'] }} - {{ $member['role'] }}</li>
            @endforeach
        </ul>
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="/css/about.css">
@endpush

@push('scripts')
    <script src="/js/about.js"></script>
@endpush
```

### Using Child Template

```php
<?php
$data = [
    'showTeam' => true,
    'teamMembers' => [
        ['name' => 'John Doe', 'role' => 'CEO'],
        ['name' => 'Jane Smith', 'role' => 'CTO'],
        ['name' => 'Bob Johnson', 'role' => 'Developer']
    ]
];

echo (new View($viewEngine, 'pages.about', $data))->render();
```

---

## Components with Slots

### Creating Components

Components should be stored in `views/components/` and use snake_case filenames.

#### Simple Button Component

**File: `views/components/button.plug.php`**

```php
<button 
    type="{{ $type ?? 'button' }}" 
    class="btn btn-{{ $variant ?? 'primary' }}"
    @if(isset($disabled) && $disabled)
        disabled
    @endif
>
    {{ $slot }}
</button>
```

#### Card Component with Slot

**File: `views/components/card.plug.php`**

```php
<div class="card {{ $class ?? '' }}">
    @if(isset($title))
        <div class="card-header">
            <h3>{{ $title }}</h3>
        </div>
    @endif
    
    <div class="card-body">
        {{{ $slot }}}
    </div>
    
    @if(isset($footer))
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endif
</div>
```

#### Alert Component

**File: `views/components/alert.plug.php`**

```php
<div class="alert alert-{{ $type ?? 'info' }}" role="alert">
    @if(isset($title))
        <strong>{{ $title }}</strong><br>
    @endif
    {{{ $slot }}}
</div>
```

### Using Components in Views

**File: `views/dashboard.plug.php`**

```php
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>
    
    {{-- Self-closing component --}}
    <Button variant="primary" type="submit">Submit Form</Button>
    
    {{-- Component with slot content --}}
    <Card title="User Statistics" class="mt-4">
        <p>Total Users: {{ $totalUsers }}</p>
        <p>Active Users: {{ $activeUsers }}</p>
        
        {{-- Nested component --}}
        <Button variant="success">View Details</Button>
    </Card>
    
    {{-- Alert component --}}
    <Alert type="warning" title="Important Notice">
        Please update your profile information before {{ $deadline }}.
    </Alert>
    
    {{-- Component with dynamic attributes --}}
    <Button variant=$btnVariant disabled=$isDisabled>
        {{ $btnText }}
    </Button>
    
    {{-- Component with complex slot content --}}
    <Card title="Recent Activity">
        <ul>
            @foreach($activities as $activity)
                <li>
                    <strong>{{ $activity['user'] }}</strong> 
                    {{ $activity['action'] }} 
                    <em>{{ $activity['time'] }}</em>
                </li>
            @endforeach
        </ul>
    </Card>
@endsection
```

### Using Components

```php
<?php
$data = [
    'totalUsers' => 1250,
    'activeUsers' => 890,
    'deadline' => '2025-12-31',
    'btnVariant' => 'danger',
    'btnText' => 'Delete Account',
    'isDisabled' => false,
    'activities' => [
        ['user' => 'John', 'action' => 'logged in', 'time' => '2 mins ago'],
        ['user' => 'Jane', 'action' => 'updated profile', 'time' => '5 mins ago'],
        ['user' => 'Bob', 'action' => 'created post', 'time' => '10 mins ago']
    ]
];

echo (new View($viewEngine, 'dashboard', $data))->render();
```

---

## Directives Reference

### Conditional Directives

```php
{{-- If/Else --}}
@if($user->isAdmin())
    <p>Admin Panel Access</p>
@elseif($user->isModerator())
    <p>Moderator Panel Access</p>
@else
    <p>User Panel Access</p>
@endif

{{-- Unless (opposite of if) --}}
@unless($user->isBanned())
    <p>Welcome back!</p>
@endunless

{{-- Isset --}}
@isset($user->email)
    <p>Email: {{ $user->email }}</p>
@endisset

{{-- Empty --}}
@empty($posts)
    <p>No posts available</p>
@endempty
```

### Loop Directives

```php
{{-- Foreach --}}
@foreach($users as $user)
    <div class="user">
        <h3>{{ $user['name'] }}</h3>
        <p>{{ $user['email'] }}</p>
    </div>
@endforeach

{{-- For loop --}}
@for($i = 0; $i < 10; $i++)
    <p>Item {{ $i }}</p>
@endfor

{{-- While loop --}}
@while($value < 100)
    <p>Value: {{ $value }}</p>
    @php($value += 10)
@endwhile

{{-- Break and Continue --}}
@foreach($items as $item)
    @if($item['hidden'])
        @continue
    @endif
    
    <p>{{ $item['name'] }}</p>
    
    @if($item['isLast'])
        @break
    @endif
@endforeach

{{-- Conditional break/continue --}}
@foreach($items as $item)
    @continue($item['skip'])
    @break($item['stop'])
    <p>{{ $item['name'] }}</p>
@endforeach
```

### Include Directive

```php
{{-- Include another view --}}
@include('partials.header')

{{-- Include with data --}}
@include('partials.sidebar', ['active' => 'dashboard'])

{{-- Include with merged data --}}
@include('partials.user-card', ['user' => $currentUser, 'showEmail' => true])
```

### PHP Directives

```php
{{-- Inline PHP --}}
@php($total = $price * $quantity)

{{-- Multi-line PHP block --}}
@php
    $discount = 0;
    if ($user->isPremium()) {
        $discount = 0.2;
    }
    $finalPrice = $total * (1 - $discount);
@endphp

<p>Total: ${{ number_format($finalPrice, 2) }}</p>
```

### Once Directive

```php
{{-- Execute only once, even if included multiple times --}}
@once
    <script src="/js/jquery.js"></script>
@endonce
```

### Error Directive

```php
{{-- Display validation errors --}}
@error('email')
    <div class="error">{{ $errors->first('email') }}</div>
@enderror

@error('password')
    <div class="error">{{ $errors->first('password') }}</div>
@enderror
```

### Form Directives

```php
<form method="POST" action="/submit">
    {{-- CSRF Token --}}
    @csrf
    
    {{-- Method spoofing for PUT/PATCH/DELETE --}}
    @method('PUT')
    
    <input type="text" name="name" value="{{ $name ?? '' }}">
    <button type="submit">Submit</button>
</form>
```

---

## Advanced Examples

### Complex Dashboard with Multiple Components

**File: `views/admin/dashboard.plug.php`**

```php
@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="dashboard-header">
        <h1>Welcome back, {{ $admin->name }}</h1>
        <p>Last login: {{ $admin->lastLogin }}</p>
    </div>
    
    <div class="stats-grid">
        @foreach($stats as $stat)
            <Card class="stat-card">
                <div class="stat-icon {{ $stat['color'] }}">
                    {{{ $stat['icon'] }}}
                </div>
                <div class="stat-content">
                    <h3>{{ $stat['value'] }}</h3>
                    <p>{{ $stat['label'] }}</p>
                </div>
                
                @if($stat['change'] > 0)
                    <Alert type="success">
                        +{{ $stat['change'] }}% from last month
                    </Alert>
                @elseif($stat['change'] < 0)
                    <Alert type="danger">
                        {{ $stat['change'] }}% from last month
                    </Alert>
                @endif
            </Card>
        @endforeach
    </div>
    
    <div class="recent-activity">
        <Card title="Recent Orders">
            @empty($orders)
                <Alert type="info">No recent orders</Alert>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>#{{ $order['id'] }}</td>
                                <td>{{ $order['customer'] }}</td>
                                <td>${{ number_format($order['amount'], 2) }}</td>
                                <td>
                                    @if($order['status'] === 'completed')
                                        <span class="badge badge-success">Completed</span>
                                    @elseif($order['status'] === 'pending')
                                        <span class="badge badge-warning">Pending</span>
                                    @else
                                        <span class="badge badge-danger">Cancelled</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endempty
        </Card>
    </div>
@endsection

@push('scripts')
    <script src="/js/dashboard-charts.js"></script>
@endpush
```

### User Profile with Nested Components

**File: `views/components/profile_card.plug.php`**

```php
<div class="profile-card {{ $size ?? 'medium' }}">
    <div class="profile-avatar">
        @if(isset($user->avatar))
            <img src="{{ $user->avatar }}" alt="{{ $user->name }}">
        @else
            <div class="avatar-placeholder">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
        @endif
    </div>
    
    <div class="profile-info">
        <h3>{{ $user->name }}</h3>
        
        @isset($user->title)
            <p class="profile-title">{{ $user->title }}</p>
        @endisset
        
        @if(isset($showEmail) && $showEmail)
            <p class="profile-email">{{ $user->email }}</p>
        @endif
        
        {{{ $slot }}}
    </div>
    
    @if(isset($showActions) && $showActions)
        <div class="profile-actions">
            <Button variant="primary" size="sm">View Profile</Button>
            <Button variant="secondary" size="sm">Send Message</Button>
        </div>
    @endif
</div>
```

**Using Profile Card:**

```php
<ProfileCard user=$currentUser showEmail=true showActions=true>
    <div class="profile-stats">
        <div class="stat">
            <strong>{{ $currentUser->postsCount }}</strong>
            <span>Posts</span>
        </div>
        <div class="stat">
            <strong>{{ $currentUser->followersCount }}</strong>
            <span>Followers</span>
        </div>
    </div>
</ProfileCard>
```

### Data Table Component

**File: `views/components/data_table.plug.php`**

```php
<div class="data-table-wrapper">
    @isset($title)
        <h3>{{ $title }}</h3>
    @endisset
    
    <table class="data-table {{ $class ?? '' }}">
        @if(isset($headers))
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        
        <tbody>
            {{{ $slot }}}
        </tbody>
    </table>
    
    @if(isset($pagination) && $pagination)
        <div class="pagination">
            {{-- Pagination controls would go here --}}
        </div>
    @endif
</div>
```

**Using Data Table:**

```php
<DataTable 
    title="User List" 
    headers=$tableHeaders 
    pagination=true
>
    @foreach($users as $user)
        <tr>
            <td>{{ $user['id'] }}</td>
            <td>{{ $user['name'] }}</td>
            <td>{{ $user['email'] }}</td>
            <td>
                <Button variant="info" size="sm">Edit</Button>
                <Button variant="danger" size="sm">Delete</Button>
            </td>
        </tr>
    @endforeach
</DataTable>
```

---

## Tips and Best Practices

### 1. Component Naming

- Use PascalCase in templates: `<Button>`, `<UserCard>`, `<DataTable>`
- Files should be snake_case: `button.plug.php`, `user_card.plug.php`, `data_table.plug.php`

### 2. Data Passing

```php
// Pass primitive values with quotes
<Button variant="primary" type="submit">

// Pass variables without quotes
<Button variant=$userRole disabled=$isDisabled>

// Pass complex expressions
<Card title="{{ $user->name . ' Profile' }}">
```

### 3. Slots

- Use `{{ $slot }}` for escaped slot content
- Use `{{{ $slot }}}` for raw HTML slot content
- Slots inherit the parent view's data context

### 4. Caching

```php
// Enable caching in production
$viewEngine = new ViewEngine(
    viewPath: __DIR__ . '/views',
    cachePath: __DIR__ . '/cache/views',
    cacheEnabled: $_ENV['APP_ENV'] === 'production'
);

// Clear cache when needed
$viewEngine->clearCache();
```

### 5. Error Handling

```php
try {
    echo (new View($viewEngine, 'page', $data))->render();
} catch (\RuntimeException $e) {
    // Handle view not found or rendering errors
    error_log($e->getMessage());
    echo "Error loading page";
}
```
