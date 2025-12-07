# Plugs PHP Framework

![Project Logo](/docs/logo.png)

[![Latest Version](https://img.shields.io/packagist/v/plugs/plugs.svg?style=flat-square)](https://packagist.org/packages/plugs/plugs)
[![Total Downloads](https://img.shields.io/packagist/dt/plugs/plugs.svg?style=flat-square)](https://packagist.org/packages/plugs/plugs)
[![License](https://img.shields.io/packagist/l/plugs/plugs.svg?style=flat-square)](https://packagist.org/packages/plugs/plugs)

**Created by [Amisu Usman (Celio Natti)](https://github.com/celionatti)**

Plugs is a modern, lightweight PHP framework built for developers who value **speed, structure, and flexibility**.  
It’s designed to keep your code clean, modular, and easy to scale — giving you full control without the chaos.

> **Your power source for clean, connected, and creative PHP development.** ⚙️

## 🚀 Features

- **🧩 Modular Architecture**: Built on top of PSR-7 and PSR-15 standards for maximum interoperability.
- **⚡ Powerful Routing**: Laravel-style routing with support for groups, prefixes, subdomains, and method spoofing.
- **🗄️ Eloquent ORM**: Includes the full power of Laravel's Eloquent ORM for effortless database interactions.
- **🎨 Plugs View Information**: A robust, component-based view engine with custom directives, caching, and layouts.
- **🔒 Built-in Security**: Input normalization, CSRF protection, and secure session handling.
- **🧠 Intuitive Design**: Clean syntax that gets out of your way and lets you build fast.

## 📋 Requirements

- PHP >= 8.0
- Composer

## 📦 Installation

To get started, install Plugs via Composer:

```bash
composer require plugs/plugs
```

## 🏁 Quick Start

### 1. Bootstrapping the Application

Create an `index.php` file in your public directory to initialize the framework.

```php
use Plugs\Plugs;

require __DIR__ . '/../vendor/autoload.php';

$app = new Plugs();

// Run the application
$app->run();
```

### 2. Routing

Plugs features a powerful router that feels right at home if you've used modern PHP frameworks.

```php
use Plugs\Router\Router;

$router = new Router();

// Basic Routes
$router->get('/', function() {
    return 'Welcome to Plugs!';
});

$router->get('/user/{id}', [UserController::class, 'show']);

// Route Groups
$router->group(['prefix' => 'api', 'middleware' => [AuthMiddleware::class]], function($router) {
    $router->get('/users', [ApiController::class, 'index']);
    $router->post('/users', [ApiController::class, 'store']);
});

// Resource Routing
$router->resource('posts', PostController::class);
```

### 3. Database Setup (Eloquent)

Plugs comes with Eloquent ORM pre-packaged. Initialize it easily:

```php
use Plugs\Database\EloquentBootstrap;

$config = [
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'plugs_db',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
];

EloquentBootstrap::boot($config);
```

### 4. Component-Based Views

The Plugs View Engine allows you to build reusable UI components.

**Layout (`views/layouts/app.plug.php`):**
```php
<!DOCTYPE html>
<html>
<body>
    <nav>...</nav>
    <main>
        @yield('content')
    </main>
</body>
</html>
```

**Page (`views/home.plug.php`):**
```php
@extends('layouts.app')

@section('content')
    <h1>Hello, {{ $name }}</h1>
    
    <Alert type="success">
        Operation completed!
    </Alert>
@endsection
```

## 📂 Recommended Directory Structure

Keep your project organized with this proven structure:

```
your-project/
├── app/
│   ├── Controllers/
│   ├── Models/
│   └── Middleware/
├── config/
├── public/
│   └── index.php
├── routes/
│   └── web.php
├── views/
│   ├── components/
│   ├── layouts/
│   └── pages/
├── storage/
│   ├── cache/
│   └── logs/
└── vendor/
```

## 🔒 Security

If you discover any security-related issues, please email <celionatti@gmail.com> instead of using the issue tracker.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

Plugs is open-sourced software licensed under the [Apache-2.0 license](LICENSE).
