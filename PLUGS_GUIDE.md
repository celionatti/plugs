# Plugs Framework — Developer's Complete Guide

Welcome to the comprehensive reference manual for the **Plugs Framework** — a high-performance, modular, and AI-native core engine for modern PHP applications.

This guide details the framework architecture, features, core functions, CLI commands, and provides a step-by-step walkthrough for building, configuring, and deploying your projects.

---

## 📖 Table of Contents
1. [Architecture & Lifecycle](#1-architecture--lifecycle)
2. [Directory Structure](#2-directory-structure)
3. [Core Components](#3-core-components)
   * [Service Container & Dependency Injection](#service-container--dependency-injection)
   * [Routing & Middleware](#routing--middleware)
   * [Controllers, Requests, & Responses](#controllers-requests--responses)
   * [Active Record ORM & Query Builder](#active-record-orm--query-builder)
   * [View V5 Template Engine](#view-v5-template-engine)
   * [AI-Native Capabilities & Agentic Workflows](#ai-native-capabilities--agentic-workflows)
   * [Asynchronous Queues & Event Bus](#asynchronous-queues--event-bus)
   * [Real-Time SSE Multiplexing](#real-time-sse-multiplexing)
   * [Security Shield & CSRF](#security-shield--csrf)
4. [CLI Command Reference](#4-cli-command-reference)
5. [Quickstart: Building Your First Project](#5-quickstart-building-your-first-project)

---

## 1. Architecture & Lifecycle

Plugs follows a **single-entry, request-response lifecycle** modeled after modern PSR standards. It utilizes a context-aware bootstrapper to determine if a request comes from the web browser (HTTP), the CLI console, or a real-time ReactPHP daemon.

### The Request Lifecycle:
```
[User Request] ➔ [public/index.php] ➔ [bootstrap/boot.php]
                                              │
                      ┌───────────────────────┴───────────────────────┐
                      ▼                                               ▼
            [HTTP Web Context]                               [CLI Console Context]
                      │                                               │
             [Resolve Container]                              [Resolve Container]
                      │                                               │
            [Global Middlewares]                             [Parse Arguments/Opts]
                      │                                               │
               [Route Matching]                               [Execute Command]
                      │                                               │
           [Route-specific Middlewares]                               ▼
                      │                                            [Output]
             [Controller Action]
                      │
              [Render View V5]
                      │
                      ▼
               [Send Response]
                      │
               [Terminate App]
```

---

## 2. Directory Structure

A standard Plugs application scaffold is organized modularly to decouple the framework core from your application logic:

```
├── app/                      # Main Application Namespace
│   ├── Console/              # App-specific Console Commands
│   ├── Http/                 # Controllers & Middlewares
│   │   ├── Controllers/      # Request Controllers
│   │   └── Middlewares/      # Request Middlewares
│   └── Providers/            # Service Providers (Bindings)
├── bootstrap/                # Application Bootstrapping Code
│   └── boot.php              # Application initial entry setup
├── config/                   # App Configuration Files
│   ├── app.php               # General App config (debug, url, key)
│   ├── database.php          # Connection configs
│   ├── modules.php           # Active framework extensions
│   └── cache.php             # Redis, Memcached, File cache options
├── database/                 # Database Migrations & Seeds
│   ├── migrations/           # DB schema evolution scripts
│   └── seeders/              # Populate initial database records
├── modules/                  # Extensible Modules (e.g. Auth, Admin)
├── public/                   # Public Web Root
│   ├── index.php             # Main entry file
│   ├── install/              # Web Installer (Requirements, DB config)
│   └── assets/               # CSS, JS, Images cache files
├── resources/                # Front-End Assets & Views
│   └── views/                # View V5 Templates (.plug.php)
├── routes/                   # App Routing Files
│   ├── web.php               # Browser HTTP Routes
│   └── api.php               # Stateless REST API Routes
├── storage/                  # Generated Caches, Logs, and Uploads
│   ├── framework/            # Compiled route/config caches
│   ├── logs/                 # System error logs
│   └── views/                # Compiled View cache files
├── theplugs                  # Main CLI executable binary
└── composer.json             # PHP dependencies definitions
```

---

## 3. Core Components

### Service Container & Dependency Injection
Plugs features a PSR-11 compliant Dependency Injection (DI) container that manages class instances, handles auto-wiring, and manages shared singletons.

#### Binding Instances:
```php
use Plugs\Container\Container;

$container = Container::getInstance();

// Simple Binding
$container->bind(MailerInterface::class, SymfonyMailer::class);

// Singleton Binding
$container->singleton(DatabaseConnection::class, function($c) {
    return new DatabaseConnection(config('database.connections.mysql'));
});
```

#### Auto-wiring Injection:
```php
namespace App\Http\Controllers;

use App\Services\PaymentService;

class CheckoutController
{
    // PaymentService is auto-injected by the Plugs Container
    public function __construct(private PaymentService $payment) {}

    public function process()
    {
        $this->payment->charge();
    }
}
```

---

### Routing & Middleware
Routes map URLs to callback functions or controller actions. You can protect routes using Middlewares.

#### Registering Routes (`routes/web.php`):
```php
use Plugs\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Middlewares\AuthMiddleware;

// Simple Route
Route::get('/', [HomeController::class, 'index']);

// Group with Middleware
Route::group(['middleware' => [AuthMiddleware::class]], function() {
    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::post('/settings', [SettingsController::class, 'save']);
});
```

---

### Controllers, Requests, & Responses
Controllers handle input payload validations and build application logic.

#### Sample Controller:
```php
namespace App\Http\Controllers;

use Plugs\Http\Request;
use Plugs\Http\Response;
use App\Models\User;

class UserController
{
    public function store(Request $request): Response
    {
        // 1. Validate Input
        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);

        // 2. Create Record
        $user = User::create([
            'email' => $validated['email'],
            'password' => password_hash($validated['password'], PASSWORD_DEFAULT)
        ]);

        // 3. Return JSON Response
        return response()->json([
            'success' => true,
            'user_id' => $user->id
        ], 201);
    }
}
```

---

### Active Record ORM & Query Builder
The ORM provides an intuitive, ActiveRecord implementation for working with your database.

#### Query Builder (`DB` Facade):
```php
use Plugs\Facades\DB;

// Fetch multiple rows
$users = DB::table('users')
    ->where('role', '=', 'admin')
    ->orderBy('created_at', 'DESC')
    ->get();
```

#### ActiveRecord ORM:
```php
use App\Models\User;

// Find record
$user = User::find(1);
$user->name = "Updated Name";
$user->save(); // Updates DB

// Create record
$newUser = new User();
$newUser->email = "test@example.com";
$newUser->save(); // Inserts DB
```

---

### View V5 Template Engine
Plugs utilizes a state-of-the-art template engine supporting modern syntax, layout inheritance, and automatic contextual HTML escaping.

#### Defining a Layout (`views/layouts/app.plug.php`):
```html
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'Plugs App' }}</title>
</head>
<body>
    <header>My App Header</header>
    
    <main>
        <slot></slot> <!-- Target for content injection -->
    </main>
</body>
</html>
```

#### Creating a View (`views/welcome.plug.php`):
```html
<layout name="layouts.app">
    <title>Welcome Page</title>

    <h1>Welcome back, {{ $username }}</h1>

    <if condition="$isAdmin">
        <div class="admin-panel">Admin settings here</div>
    </if>

    <loop items="$items" as="$item">
        <p>Item name: {{ $item->name }}</p>
    </loop>
</layout>
```

---

### AI-Native Capabilities & Agentic Workflows
Plugs has native integrations with Gemini, OpenAI, Anthropic, and Ollama drivers, allowing you to easily build AI features.

#### Querying AI:
```php
use Plugs\AI\AI;

$response = AI::driver('gemini')->generateText("Write a 50-word description of a lightweight PHP framework.");
echo $response;
```

#### Autonomous Agents:
```php
use Plugs\AI\Agent;

$agent = new Agent([
    'driver' => 'gemini',
    'instructions' => 'You are an automated code assistant. Write clean, PHP-compatible response code.'
]);

$result = $agent->task("Write a PHP script to calculate Fibonacci sequence numbers up to 10.");
```

---

### Asynchronous Queues & Event Bus
Process resource-intensive tasks (e.g., sending emails, resizing photos) asynchronously to keep your web requests blazing fast.

#### Dispatching a Job:
```php
use Plugs\Facades\Queue;
use App\Jobs\SendWelcomeEmail;

// Push task to background queue
Queue::push(new SendWelcomeEmail($user->id));
```

#### Running Workers:
```bash
php theplugs queue:work
```

---

### Real-Time SSE Multiplexing
Build dynamic, real-time features like chat applications and notification streams without setting up complex WebSocket servers.

#### Broadcasting a Message:
```php
use Plugs\SSE\Broadcaster;

Broadcaster::channel('notifications')->send([
    'event' => 'new-signup',
    'message' => 'A new developer joined Plugs!'
]);
```

---

### Security Shield & CSRF
Plugs comes out-of-the-box with **Security Shield** to prevent DDOS, block malicious bots, blacklist persistent abusers, and enforce CSRF boundaries.

#### Shield Commands:
* `php theplugs shield:block --ip=192.168.1.1` - Blocks IP.
* `php theplugs shield:stats` - Shows block stats.

---

## 4. CLI Command Reference

Here is a full breakdown of the console commands available in Plugs. Run `php theplugs` to view the active list.

### General Commands
| Command | Description |
|---|---|
| `help` | Display help instructions for commands |
| `demo` | Showcase the premium CLI UI components |
| `inspire` | Display an inspiring developer quote |
| `framework:explain` | Explain framework concepts and architecture |

### Scaffolding (Make)
| Command | Alias | Description |
|---|---|---|
| `make:controller` | `g:c` | Create a new Controller class |
| `make:model` | `g:m` | Create a new ActiveRecord Model |
| `make:command` | `g:cmd` | Create a new CLI console command |
| `make:middleware` | `g:mid` | Create a new Request Middleware |
| `make:provider` | `g:prov` | Create a new Service Provider |
| `make:migration` | `g:mig` | Create a new DB schema migration file |
| `make:seeder` | `g:seed` | Create a new database table records seeder |
| `make:crud` | `g:crud` | Scaffold full CRUD (Model, Migration, Controller, Views) |
| `make:welcome` | | Reset welcome page to the default theme design |
| `make:pdf-template` | | Generate a professional PDF invoice or resume template |

### Utilities & Optimization
| Command | Alias | Description |
|---|---|---|
| `serve` | `s` | Start the PHP built-in local development server |
| `tinker` | `t` | Interact with your application in an interactive REPL shell |
| `env:sync` | `sync` | Synchronize your active `.env` file keys with `.env.example` |
| `cache:clear` | `cc` | Clear the application runtime cache |
| `logs:clear` | `lc` | Clear the framework error logs |
| `config:cache` | | Create a configuration cache file for production |
| `config:clear` | | Remove the configuration cache file |
| `container:clear` | | Clear the container cache file |
| `optimize` | `oc` | Cache framework files (Config, Route, Container, View) for production speed |
| `optimize:clear` | | Clear all cached configurations, routes, views, and container files |
| `key:generate` | | Generate a secure application encryption key and write to `.env` |
| `app:install` | | Install Plugs Framework applications via CLI (Interactive or Headless) |
| `install:cleanup` | | Safely deletes the `public/install/` setup directory |

### Routing
| Command | Alias | Description |
|---|---|---|
| `route:list` | `routes` | Display all registered routes in a beautiful tabular grid |
| `route:cache` | | Cache all routes to speed up matching resolution |
| `route:clear` | | Remove the route cache file |
| `route:openapi` | | Generate OpenAPI JSON specification from routes |

### Database & Migrations
| Command | Alias | Description |
|---|---|---|
| `migrate` | `m` | Run all pending database migrations |
| `migrate:rollback` | `m:r` | Rollback the last database migration batch |
| `migrate:status` | `m:s` | Show the status of each migration |
| `migrate:fresh` | `fresh` | Drop all database tables and re-run all migrations |
| `db:seed` | `seed` | Seed the database with records |
| `db:backup` | `dbb` | Backup the database (SQL dump) |
| `db:restore` | | Restore database from a backup file |

---

## 5. Quickstart: Building Your First Project

### Step 1: Create the Project Scaffolding
Create a new directory for your project and initialize the framework:

```bash
composer create-project theplugs/theplugs my-new-project
cd my-new-project
```

### Step 2: Install and Configure
Run the CLI installer to check your system requirements, test your database connections, set your environment variables, and create your initial admin account:

```bash
php theplugs app:install
```
*(Follow the interactive wizard prompts to configure MySQL/PostgreSQL/SQLite, setup your application URL, and configure your Admin credentials).*

### Step 3: Run the Application
Start the local server:
```bash
php theplugs serve
```
Open your browser and visit `http://localhost:8000` to see your new welcome page.

### Step 4: Generate Your First Page
Create a model, migration, and controller using our CRUD scaffolding command:

```bash
php theplugs make:crud Article
```

Run migrations to create the `articles` table in your database:
```bash
php theplugs migrate
```

Open `routes/web.php` and map your new articles controller:
```php
use App\Http\Controllers\ArticleController;

Route::get('/articles', [ArticleController::class, 'index']);
```

### Step 5: Clean Up and Optimize for Production
Once you're ready to deploy to production:

1. **Delete installer files**:
   ```bash
   php theplugs install:cleanup
   ```
2. **Speed up performance**:
   ```bash
   php theplugs optimize
   ```

You are now ready to build powerful, AI-native PHP applications with the Plugs Framework! ⚡
