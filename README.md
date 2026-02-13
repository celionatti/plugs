<p align="center">
  <a href="https://github.com/celionatti/plugs">
    <img src="https://raw.githubusercontent.com/celionatti/plugs/main/art/logo.png" alt="Plugs Framework Logo" width="300">
  </a>
</p>

<h1 align="center">Plugs Framework</h1>

<p align="center">
  <strong>The High-Performance, AI-Native Core Engine for Modern PHP.</strong>
</p>

<p align="center">
  <a href="#features">Features</a> •
  <a href="#installation">Installation</a> •
  <a href="#setup">Setup & Installer</a> •
  <a href="#documentation">Documentation</a>
</p>

---

## ⚡ Introduction

**Plugs** is the lightweight, blazing-fast **core engine** that powers the **ThePlugs** application framework. Designed for speed, structure, and developer happiness, it serves as the beating heart of your PHP applications.

- **Plugs (`plugs/plugs`)**: The Core Framework Library (Engine).
- **ThePlugs (`theplugs/theplugs`)**: The Application Skeleton (Starter Project).

Whether you are building a simple API, a complex web application, or an AI-powered agent, Plugs provides the robust foundation you need.

## 🚀 Key Features

### 🏎️ Performance First

- **Built-in OPcache Management**: Production-ready caching strategies out of the box.
- **Fast Routing**: Optimized route matching algorithm.
- **Minimal Footprint**: Low memory overhead.

### 🧠 AI-Native

- **Integrated AI Drivers**: First-class support for **Gemini**, **Anthropic**, **OpenAI**, and **Ollama**.
- **Agentic Workflows**: Build autonomous agents with the built-in `Agent` class.
- **CLI AI Tools**: Chat and fix code directly from the terminal (`ai:chat`, `ai:fix`).

### 📦 Full-Stack Capable

- **Web Installer**: A beautiful, built-in installer to set up your environment, database, and admin account in seconds.
- **View Engine**: Powerful templating with component support and caching.
- **Database**: Fluent query builder and Active Record ORM.
- **Security**: Built-in Shield, CSRF protection, and encryption.

## 📦 Installation

### Option 1: Start a New Project (Recommended)

To create a new application using the Plugs architecture, use the **ThePlugs** skeleton:

```bash
composer create-project theplugs/theplugs my-app
```

### Option 2: Install Core Engine (Integration)

If you already have a project structure and want to integrate the **Plugs Core Engine**:

```bash
composer require plugs/plugs
```

> **Note:** The core package includes a `public/install` folder that assists in generating the necessary file structure and configuration for your project.

## 🛠️ Setup & Web Installer

One of the standout features of Plugs is its zero-friction setup. Once you have installed the framework:

1.  **Start the Server**:
    ```bash
    php theplugs serve
    ```
2.  **Run the Installer**:
    Navigate to `http://localhost:8000/install` in your browser.

    The installer will guide you through:
    - ✅ **System Requirements Check**
    - 🗄️ **Database Configuration** (MySQL, PostgreSQL, SQLite)
    - ⚙️ **Application Settings**
    - 👤 **Admin Account Creation**

    Once completed, your application framework—folders, configuration, and database—will be fully generated and ready to go!

## 🏁 Quick Start

### Routing

Define routes in `routes/web.php`:

```php
use Plugs\Router\Route;

Route::get('/', function () {
    return view('welcome');
});
```

### Controllers

Generate a controller using the CLI:

```bash
php theplugs make:controller UserController
```

### AI Chat

Interact with your AI driver immediately:

```bash
php theplugs ai:chat "Explain how the Service Container works"
```

## 🤝 Contributing

We welcome contributions to the Core Engine! Please see our [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 📄 License

The Plugs Framework is open-sourced software licensed under the **[Apache 2.0 License](LICENSE)**.

---

<p align="center">
Designed & Developed by <strong>Celio Natti</strong>
</p>
