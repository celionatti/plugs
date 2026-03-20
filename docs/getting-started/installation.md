# Installation Guide

Follow these steps to set up your environment and create your first **Plugs** project.

---

## 1. Prerequisites

Before installing Plugs, ensure your local machine meets the following requirements:

- **PHP**: 8.1 or higher (PHP 8.2+ recommended for Fibers/Concurrency support).
- **Composer**: Latest version.
- **Extensions**: `pdo_mysql`, `openssl`, `mbstring`, `xml`, `ctype`, `json`, `bcmath`, `curl`.
- **Database**: MySQL 5.7+ (or MariaDB), PostgreSQL, or SQLite.

---

## 2. Create Your Project

The most common way to install Plugs is via Composer's `create-project` command:

```bash
composer create-project plugs/plugs-skeleton my-app
```

Alternatively, if you're adding Plugs to an existing Composer project:

```bash
composer require plugs/plugs
```

---

## 3. Directory Permissions

Plugs requires write access to the `storage` and `bootstrap/cache` directories. If you're on a Linux/macOS system, run:

```bash
chmod -R 775 storage bootstrap/cache
```

---

## 4. Environment Configuration

1. **Initialize .env**: Copy the provided template.
   ```bash
   cp .env.example .env
   ```
2. **Generate App Key**: This key is used for session encryption and secure cookies.
   ```bash
   php theplugs key:generate
   ```
3. **Database Setup**: Open `.env` and configure your `DB_HOST`, `DB_DATABASE`, and credentials.

---

## 5. Local Development Server

You can quickly serve your application using the built-in CLI tool:

```bash
php theplugs serve
```

Your application will be live at `http://localhost:8000`.

---

## Next Steps

Now that you're up and running, learn how to [Configure Your Application](./configuration.md) or dive into [Core Architecture](../architecture/lifecycle.md).
